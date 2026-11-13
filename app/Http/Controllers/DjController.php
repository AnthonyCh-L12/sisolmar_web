<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveDeclaracionJuradaRequest;
use App\Services\DjService;
use App\Services\PdfService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\ExcepcionEdadMail;
use App\Models\NotificacionModel;

class DjController extends Controller
{
    /**
     * Rol "ADMINS RRHH" (sw_roles.codigo). Unico rol que puede autorizar
     * excepciones de edad al registrar o editar una DJ.
     */
    private const ROL_ADMIN_RRHH = 17;

    public function generarPDF(Request $request)
    {
        $data = $request->all();

        $pdfService = new PdfService;
        $pdfContent = $pdfService->generarPdf($data);

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="previsualizacion_dj.pdf"');
    }

    public function saveReporteAvanceDj(Request $request)
    {
        $data = $request->validate([
            'codPersonal' => [
                'required',
                'string',
                'size:5', 
            ],
            'estFirma'  => [
                'required',
                'boolean',
            ],
            'estHuella' => [
                'required',
                'boolean',
            ],
            'observacion' => [
                'required',
                'string',
                'max:500'
            ],
        ]);

        try {
            DB::table('sw_log_migracion_huella_firma')->updateOrInsert(
                [
                    'codiPers' => $data['codPersonal'],
                ],
                [
                    'estFirma'          => $data['estFirma'],
                    'estHuella'         => $data['estHuella'],
                    'creadoPor'         => 'MIGRACION',
                    'observacion'       => $data['observacion'],
                    'fechaModificacion' => DB::raw('GETDATE()'),
                ]
            );
        }  catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Error al guardar el registro',
                'detail'  => $e->getMessage(), 
                'line'    => $e->getLine(),     
                'file'    => $e->getFile(),   
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Proceso realizado correctamente'
        ], 200);
    }

   

        public function saveNuevaDj(Request $request)
    {
        DB::beginTransaction();
 
        try {
            $data    = $request->all();
            $usuarioActual = strtoupper(trim((string)(session('usuario') ?? '')));
            $exentoValidaciones = in_array('exento_validaciones', session('funcionalidades', []));
            $dni     = trim($request->input('dni', ''));
            $tipoPer = trim($request->input('tipo_personal', ''));

            // Siempre se leen: aunque el exento no esté obligado a adjuntarlos,
            // si los envía se suben igual.
            $fotoReq       = $request->file('foto');
            $dniAnversoReq = $request->file('dni_anverso');
            $dniReversoReq = $request->file('dni_reverso');

            // La nueva DJ exige el DNI de ambos lados para cada hijo menor
            // registrado y para cada hijo mayor con discapacidad permanente.
            $errorDniHijos = $this->validarDniHijosRequeridos($request, $data, null, false);
            if ($errorDniHijos) {
                DB::rollBack();
                return response()->json($errorDniHijos, 422);
            }

            if (!$exentoValidaciones) {
                // ── Documentos obligatorios: foto del personal + DNI (anverso y reverso) ──
                // Sin ellos no se crea el personal (solo exentos se lo saltan).
                $faltanPartes = [];
                if (!$fotoReq)                          $faltanPartes[] = 'foto del personal';
                if (!$dniAnversoReq || !$dniReversoReq) $faltanPartes[] = 'foto del DNI';

                if (!empty($faltanPartes)) {
                    DB::rollBack();
                    return response()->json([
                        'success'        => false,
                        'code'           => !$fotoReq ? 'falta_foto' : 'falta_dni',
                        'message'        => 'Falta ingresar ' . implode(' y ', $faltanPartes) . '.',
                        'falta_foto'     => !$fotoReq,
                        'falta_anverso'  => !$dniAnversoReq,
                        'falta_reverso'  => !$dniReversoReq,
                    ], 422);
                }

                foreach ([$fotoReq, $dniAnversoReq, $dniReversoReq] as $archivo) {
                    $extension = strtolower((string) $archivo->getClientOriginalExtension());
                    if (!in_array($extension, ['jpg', 'jpeg'], true)) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'code'    => 'documento_invalido',
                            'message' => 'La foto del personal y la del DNI deben ser imágenes JPG.',
                        ], 422);
                    }
                    if ($archivo->getSize() > 10 * 1024 * 1024) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'code'    => 'documento_invalido',
                            'message' => 'Cada imagen no debe superar los 10 MB.',
                        ], 422);
                    }
                }

                if (empty($dni)) {
                    return response()->json(['success' => false, 'message' => 'El DNI es requerido.'], 400);
                }

                if ($tipoPer === '') {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Debe seleccionar el tipo de trabajador.'], 422);
                }

                $tipoValido = DB::selectOne(
                    'SELECT 1 FROM si_solm.dbo.ADMI_TIPO_PERSONAL WHERE TIPE_CODIGO = ?',
                    [$tipoPer]
                );
                if (!$tipoValido) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'El tipo de trabajador seleccionado no es válido.'], 422);
                }

                $fechaNacimiento = trim($request->input('fecha_nacimiento', ''));
                $fechaNacimientoCarbon = $this->parsearFechaNacimiento($fechaNacimiento);
                if (!$fechaNacimientoCarbon) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'La fecha de nacimiento es obligatoria y debe tener formato válido.'], 422);
                }

                $fechaNacimiento = $fechaNacimientoCarbon->format('Y-m-d');

                if ($fechaNacimientoCarbon->isToday() || $fechaNacimientoCarbon->isFuture()) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'La fecha de nacimiento debe ser anterior a hoy.'], 422);
                }

                $edad = $fechaNacimientoCarbon->age;
                $reglasEdad = $this->obtenerReglasEdad();
                if ($edad < $reglasEdad['minima'] || $edad > $reglasEdad['maxima']) {
                    // Debe existir una excepción autorizada por un ADMIN RRHH
                    // para esta misma fecha de nacimiento (se pide en cada registro).
                    $excepcionValida = $this->excepcionEdadAutorizada($fechaNacimiento, $edad);

                    if (!$excepcionValida) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "La edad calculada ({$edad} años) está fuera del rango permitido de {$reglasEdad['minima']} a {$reglasEdad['maxima']} años.",
                            'edad_fuera_rango' => true,
                            'edad' => $edad,
                            'edad_minima' => $reglasEdad['minima'],
                            'edad_maxima' => $reglasEdad['maxima'],
                        ], 422);
                    }
                }
            } else {
                $fechaNacimiento = trim($request->input('fecha_nacimiento', ''));
                $fechaNacimientoCarbon = $this->parsearFechaNacimiento($fechaNacimiento);
                if ($fechaNacimientoCarbon) {
                    $fechaNacimiento = $fechaNacimientoCarbon->format('Y-m-d');
                } else {
                    $fechaNacimiento = null;
                }
            }
 
            // Verificar que no exista ya en PERSONAL por DNI
            $existente = DB::selectOne(
                'SELECT CODI_PERS FROM si_solm.dbo.PERSONAL WHERE NRO_DOCU_IDEN = ?',
                [$dni]
            );
            if ($existente) {
                return response()->json([
                    'success' => false,
                    'message' => "Ya existe un registro con el DNI {$dni} (Cód: {$existente->CODI_PERS})."
                ], 422);
            }
 
            // Generar CODI_PERS incremental
            $maxCod = DB::selectOne(
                "SELECT MAX(CAST(CODI_PERS AS INT)) AS max_cod 
                FROM si_solm.dbo.PERSONAL 
                WHERE ISNUMERIC(CODI_PERS) = 1 
                AND LEN(LTRIM(RTRIM(CODI_PERS))) = 5"
            );
            $siguiente = (int)($maxCod->max_cod ?? 0) + 1;
            if ($siguiente > 99999) {
                return response()->json(['success' => false, 'message' => 'Límite máximo de códigos alcanzado.'], 500);
            }
            $nuevoCod = str_pad($siguiente, 5, '0', STR_PAD_LEFT);
 
            $codExiste = DB::selectOne('SELECT 1 FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?', [$nuevoCod]);
            if ($codExiste) {
                return response()->json(['success' => false, 'message' => "Conflicto al generar código {$nuevoCod}."], 500);
            }
 
            // ── ✅ BLOQUE ÚNICO DE VARIABLES (eliminado el duplicado anterior) ──────
 
            // CODI_TIPO_DOCU — lee del formulario (campo ndj_tipo_documento)
            $codiTipoDocu = $request->input('tipo_documento', '0034');
 
            // Estado civil
            $estadoCivilMap = [
                '2007000001' => 'S', '2007000002' => 'C',
                '2007000003' => 'D', '2007000004' => 'V', '2007000008' => 'V',
            ];
            $estadoCivil      = $data['estado_civil'] ?? null;
            $estadoCivilCorto = $estadoCivilMap[$estadoCivil] ?? null;
 
            // PERS_TIPOTRAB almacena el código de ADMI_TIPO_PERSONAL.
            $tipotrab = $tipoPer;
 
            // Campos char/varchar cortos
            $sexo        = strtoupper(substr($data['sexo']           ?? 'M',  0, 1));
            $essalud     = strtoupper(substr($data['essalud']        ?? 'NO', 0, 2));
            $pensionista = strtoupper(substr($data['pensionista']    ?? 'NO', 0, 2));
            $embargo     = strtoupper(substr($data['embargos']       ?? 'NO', 0, 2));
            $snadar      = 'NO';
            $condiscamec = strtoupper(substr($data['curso_sucamec']  ?? 'NO', 0, 2));
            $conarmas    = strtoupper(substr($data['arma_propia']    ?? 'NO', 0, 2));
            $consmo      = strtoupper(trim($data['presto_smo'] ?? 'NO'));
            $lugarsmo    = ($consmo === 'SI' && !empty($data['lugar_smo']))
                ? strtoupper(trim($data['lugar_smo'])) : null;
            $vehiculoPropio  = strtoupper(substr($data['vehiculo_propio']  ?? 'NO', 0, 2));
            $familiarEmpresa = strtoupper(substr($data['familiar_empresa'] ?? 'NO', 0, 2));
            $vigencia        = 'SI';
            $claseBrevete    = !empty($data['clase_brevete'])
                ? strtoupper(substr($data['clase_brevete'], 0, 1)) : null;
 
            // Carrera / Institución
            $carrCodigo = null;
            $ieduCodigo = null;
            if (!empty($data['carrera']) && $data['carrera'] !== '999999') {
                $carreraExiste = DB::selectOne(
                    'SELECT 1 FROM si_solm.dbo.SUNAT_CARRERAS WHERE CARR_CODIGO = ? AND IEDU_CODIGO = ?',
                    [$data['carrera'], $data['institucion'] ?? null]
                );
                if ($carreraExiste) {
                    $carrCodigo = $data['carrera'];
                    $ieduCodigo = $data['institucion'] ?? null;
                }
            }
 
            // Fechas
            $fechaNaci   = $this->sanitizeDatetimeForPersonal($data['fecha_nacimiento'] ?? null);
            $noCaducaDni = ($data['no_caduca_dni'] ?? '0') === '1' ? 1 : 0;
            $fechaCaduca = $noCaducaDni ? null : $this->sanitizeDatetimeForPersonal($data['caduca'] ?? null);
            $fechaIngre  = $this->sanitizeDatetimeForPersonal($data['fecha_ingreso_solmar'] ?? null);
 
            // Numéricos
            $pesoIngresado = trim((string) ($data['peso'] ?? ''));
            if ($pesoIngresado !== '' && ! preg_match('/^\d{1,3}$/', $pesoIngresado)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El peso debe contener como máximo 3 dígitos.'], 422);
            }

            $tallaIngresada = trim((string) ($data['talla'] ?? ''));
            if ($tallaIngresada !== '' && ! preg_match('/^\d\.\d{2}$/', $tallaIngresada)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'La talla debe tener formato M.cm, por ejemplo 1.70.'], 422);
            }

            $anioEgresoIngresado = trim((string) ($data['anio_egreso'] ?? ''));
            if ($anioEgresoIngresado !== '' && ! preg_match('/^\d{4}$/', $anioEgresoIngresado)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El año de egreso debe tener exactamente 4 dígitos.'], 422);
            }

            $contactoEmergencia = trim((string) ($data['contacto_emergencia'] ?? ''));
            if ($contactoEmergencia !== '' && ! preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/u', $contactoEmergencia)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El campo Llamar a solo admite letras y espacios.'], 422);
            }

            $celularEmergencia = trim((string) ($data['celular_emergencia'] ?? ''));
            if ($celularEmergencia !== '' && ! preg_match('/^\d{9}$/', $celularEmergencia)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El celular de emergencia debe tener exactamente 9 dígitos.'], 422);
            }

            $ocupacionPrincipal = trim((string) ($data['ocupacion_principal'] ?? ''));
            if ($ocupacionPrincipal !== '' && ! preg_match('/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]+$/u', $ocupacionPrincipal)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Profesión / Ocupación Principal solo admite letras y espacios.'], 422);
            }

            foreach (['experiencia_anios' => 'años', 'experiencia_meses' => 'meses'] as $campo => $etiqueta) {
                $experiencia = trim((string) ($data[$campo] ?? ''));
                if ($experiencia !== '' && ! preg_match('/^\d{1,2}$/', $experiencia)) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => "Los {$etiqueta} de experiencia admiten como máximo 2 dígitos."], 422);
                }
            }

            $peso             = $pesoIngresado !== '' ? $pesoIngresado : null;
            $talla            = $tallaIngresada !== '' ? $tallaIngresada : null;
            $anioEgreso       = $anioEgresoIngresado !== '' ? (int) $anioEgresoIngresado : null;
            $experienciaAnios = is_numeric($data['experiencia_anios']?? null) ? (int)$data['experiencia_anios'] : null;
            $experienciaMeses = is_numeric($data['experiencia_meses']?? null) ? (int)$data['experiencia_meses'] : null;
 
            // Laborales
            $laboral1      = !empty(trim($data['dj2026_laboral_1'] ?? '')) ? strtoupper(trim($data['dj2026_laboral_1'])) : null;
            $laboral2      = !empty(trim($data['dj2026_laboral_2'] ?? '')) ? strtoupper(trim($data['dj2026_laboral_2'])) : null;
            $cantProfesion = ($laboral1 ? 1 : 0) + ($laboral2 ? 1 : 0);

            $sucursal = !empty(trim($data['sucursal'] ?? '')) ? strtoupper(trim($data['sucursal'])) : null;
            $usuario = !empty(trim($data['usuario'] ?? 'SISTEMA')) ? strtoupper(trim($data['usuario'])) : null;

            // Relacionar la sucursal seleccionada (SUCU_CODIGO) con la unidad operativa
            // UNID_OPER.UBICACION = SISO_SUCURSAL.SUCU_CODIGO → CODI_UNID_OPER
            $codiUnidOper = null;
            if (!empty($sucursal)) {
                $uo = DB::selectOne(
                    'SELECT TOP 1 CODI_UNID_OPER FROM si_solm.dbo.UNID_OPER WHERE UBICACION = ?',
                    [$sucursal]
                );
                $codiUnidOper = $uo->CODI_UNID_OPER ?? null;
            }

            // Cargo asignado (tabla CARGOS)
            $codiCarg = trim($data['cargo'] ?? '');
            if ($codiCarg === '') {
                $codiCarg = null;
            }
 
            // País de nacimiento (PAIS_CODIGO → NACIONALIDAD)
            $nacionalidad = !empty(trim($data['nacionalidad'] ?? ''))
                ? strtoupper(trim($data['nacionalidad'])) : null;


            $distrito_nac = !empty(trim($data['distrito_nac'] ?? '')) ? strtoupper(trim($data['distrito_nac'])) : null;
            $provincia_nac = !empty(trim($data['provincia_nac'] ?? '')) ? strtoupper(trim($data['provincia_nac'])) : null;
            $departamento_nac = !empty(trim($data['departamento_nac'] ?? '')) ? strtoupper(trim($data['departamento_nac'])) : null;

            // TIPO_CONT: O = Operativo/Especial, A = Administrativo
            $tipoContMap = ['01' => 'O', '02' => 'A', '03' => 'O', '05' => 'A', '06' => 'O'];
            $tipoCont = $tipoContMap[$tipoPer] ?? 'O';

            $personalPlaceholders = implode(',', array_fill(0, 68, '?'));
            $personalLocationPlaceholders = "?,?,?,'01',?,?,?,?,?,?";
 
            // ── INSERT EN PERSONAL ──────────────────────────────────────────────
            DB::insert(
                "INSERT INTO si_solm.dbo.PERSONAL (
                    CODI_PERS, CODI_TIPO_DOCU, NRO_DOCU_IDEN,
                    NOMB_1, NOMB_2, APEL_1, APEL_2,
                    FECH_NACI, PERS_FECHCADUCADNI,
                    FECH_INGRE,
                    SEXO, PERS_SEXO,
                    ESCI_CODIGO, ESTA_CIVI,
                    PERS_EMAIL, PERS_TELEFONO, PERS_WHATSAPP,
                    DIRECCION, PERS_DIREC_DNI,
                    PERS_ZONA_DIRDNI,
                    PERS_DEPT_ACT, PERS_PROV_ACT, PERS_DIST_ACT,
                    PERS_DPTO_DIRDNI, PERS_PROV_DIRDNI, PERS_DIST_DIRDNI,
                    TIZO_CODIGO,
                    tipo_sangr, peso_kilo, tall_metr,
                    CODI_SIST_PENS, ESSALUD, PERS_PENSIONISTA, PERS_EMBARGO,
                    PERS_GRADO_INSTRUCCION, CARR_CODIGO, IEDU_CODIGO, EGRESO_EDUCATIVO,
                    PERS_CONDISCAMEC, PERS_NRODISCAMEC,
                    PERS_LUGARSMO, PERS_CONSMO,
                    PERS_NROLICENCIA, PERS_CONARMAS,
                    PERS_BREVETE, CLASE_BREVETE, CATEGORIA_BREVETE, PERS_VEHICULO_PROPIO,
                    PERS_NOMCONTACTO, PERS_NROEMERGENCIA, PERS_EMERC_FAMILIAR,
                    PERS_CTRABANT, PERS_CARGOTRABANT, PERS_DURACIONANT,
                    dj2026_banco, NACIONALIDAD, dj2026_ocupacion_principal,
                    dj2026_experiencia_anios, dj2026_experiencia_meses, dj2026_familiar_empresa,
                    dj2026_familiar_nombre, dj2026_familiar_parentesco,
                    dj2026_laboral_1, dj2026_laboral_2, dj2026_cantprofesion,
                    PERS_TIPOTRAB, PERS_VIGENCIA,
                    PERS_SNADAR, SIP_migrado, SIP_activo, SIP_habilitado,
                    USUA_FECHA_REG, USUA_FECHA_MOD
                    , SUCU_CODIGO, CODI_UNID_OPER, USUA_CODIGO_REG, EMPR_CODIGO,
                    DEPA_CODIGO_NACI, PROVI_CODIGO_NACI, DIST_NACI, CODI_CARG, PERS_CONTRATADO, TIPO_CONT,
                    NO_CADUCA_DNI,
                    DEPARTAMENTO, PROVINCIA, DISTRITO
                ) VALUES (" . $personalPlaceholders . ", 1,1,0, GETDATE(), NULL, " . $personalLocationPlaceholders . ", ?, ?, ?, ?)",
                [
                    $nuevoCod, $codiTipoDocu, $dni,
                    strtoupper(trim($data['nombre1'] ?? '')),
                    strtoupper(trim($data['nombre2'] ?? '')),
                    strtoupper(trim($data['apellido_paterno'] ?? '')),
                    strtoupper(trim($data['apellido_materno'] ?? '')) ?: null,
                    $fechaNaci, $fechaCaduca, $fechaIngre,
                    $sexo, $sexo,
                    $estadoCivil, $estadoCivilCorto,
                    $data['correo'] ?? null,
                    $data['celular'] ?? null,
                    $data['whatsapp'] ?? null,
                    $data['direccion_actual'] ?? null,
                    $data['direccion_dni'] ?? null,
                    $data['zona_dirdni'] ?? null,
                    $data['departamento_actual'] ?? null,
                    $data['provincia_actual'] ?? null,
                    $data['distrito_actual'] ?? null,
                    $data['departamento_dni'] ?? null,
                    $data['provincia_dni'] ?? null,
                    $data['distrito_dni'] ?? null,
                    $data['tipo_zona_dni'] ?? null,
                    $data['tipo_sangre'] ?? null,
                    $peso, $talla,
                    $data['sistema_previsional'] ?? '07',
                    $essalud, $pensionista, $embargo,
                    $data['grado_instruccion'] ?? null,
                    $carrCodigo, $ieduCodigo, $anioEgreso,
                    $condiscamec, $data['sucamec_obs'] ?? null,
                    $lugarsmo,
                    $consmo,
                    $data['licencia_arma'] ?? null,
                    $conarmas,
                    $data['brevete']       ?? null,
                    $claseBrevete,
                    $data['tipo_vehiculo'] ?? null,
                    $vehiculoPropio,
                    $data['contacto_emergencia']   ?? null,
                    $data['celular_emergencia']    ?? null,
                    $data['parentesco_emergencia'] ?? null,
                    $data['empresa_anterior']  ?? null,
                    $data['cargo_anterior']    ?? null,
                    $data['duracion_anterior'] ?? null,
                    $data['cuenta_banco']        ?? null,
                    $nacionalidad,                            // ✅ país código → NACIONALIDAD
                    $data['ocupacion_principal'] ?? null,
                    $experienciaAnios,
                    $experienciaMeses,
                    $familiarEmpresa,
                    $data['familiar_nombre']     ?? null,
                    $data['familiar_parentesco'] ?? null,
                    $laboral1,
                    $laboral2,
                    $cantProfesion,
                    $tipotrab,
                    $vigencia,
                    $snadar,
                    $sucursal,
                    $codiUnidOper,
                    $usuario,
                    $departamento_nac,
                    $provincia_nac,
                    $distrito_nac,
                    $codiCarg,
                    0,
                    $tipoCont,
                    $noCaducaDni,
                    strtoupper(trim($data['departamento_actual'] ?? '') ?: ''),
                    strtoupper(trim($data['provincia_actual'] ?? '') ?: ''),
                    strtoupper(trim($data['distrito_actual'] ?? '') ?: '')
                ]
            );

            if (isset($excepcionValida) && $excepcionValida) {
                // Auditoria + notificacion a RRHH (tabla sw_dj_excepciones_edad)
                $this->registrarExcepcionEdad(
                    (string) $nuevoCod,
                    (string) $dni,
                    $fechaNacimiento,
                    (int) $edad,
                    $reglasEdad,
                    $data,
                    $tipotrab,
                    $sucursal
                );
            }

 
            // Familiares y Teléfonos
            $this->saveFamiliaresTemp($nuevoCod, $data);
            $this->migrarFamiliares_solo_nuevo($nuevoCod);
            $this->saveTelefonosTemp($nuevoCod, $data);
            $this->migrarTelefonos($nuevoCod);

            // SCTR: OP (01/03) → 'SI' automático; ADMIN (02/05) → según checkbox
            $this->aplicarScrt($nuevoCod, $data);
            $this->aplicarAsigFami($nuevoCod, $data);
            $this->aplicarConHijos($nuevoCod, $data);

            // Crear registro en DJ2026_PERSONAL para que el SP de Gestion DJ lo muestre
            $this->insertOrUpdateDJ2026Personal($nuevoCod, $data, 'nueva_dj');

            // ── Subir foto y DNI al servidor de archivos ──────────────────
            // Si el servidor de archivos falla NO se bloquea el guardado: la DJ
            // se guarda igual y se avisa qué quedó pendiente de subir.
            $subidaFoto    = $this->subirFotoAlServidor($nuevoCod, $fotoReq);
            $subidaDni     = $this->subirDniAlServidor($nuevoCod, $dniAnversoReq, $dniReversoReq);
            $fotoPendiente = !$subidaFoto['ok'];
            $dniPendiente  = !$subidaDni['ok'];
 
            DB::commit();
 
            Log::info('saveNuevaDj: Personal nuevo creado', ['CODI_PERS' => $nuevoCod, 'DNI' => $dni]);

            // ── Correo automático de bienvenida SIP (solo Operativo 5° y Administrativo 5°) ──
            if (in_array($tipoPer, ['03', '05'])) {
                $this->enviarCorreoBienvenidaSip($data, $dni, $nuevoCod);
            }

            $pendientesMsg = [];
            if ($fotoPendiente) $pendientesMsg[] = 'la foto del personal';
            if ($dniPendiente)  $pendientesMsg[] = 'el DNI';

            return response()->json([
                'success'        => true,
                'message'        => !empty($pendientesMsg)
                    ? 'Declaración Jurada guardada correctamente, pero ' . implode(' y ', $pendientesMsg) . ' quedó pendiente de subir.'
                    : 'Declaración Jurada guardada correctamente.',
                'codi_pers'      => $nuevoCod,
                'foto_pendiente' => $fotoPendiente,
                'dni_pendiente'  => $dniPendiente,
                'dni_faltan'     => $subidaDni['faltan'],
            ]);
 
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en saveNuevaDj: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function uploadFotoPersonal(Request $request)
    {
        $request->validate([
            'foto'       => 'required|file|mimes:jpg,jpeg|max:10240', // JPG, máx 10 MB
            'codi_pers'  => 'required|string',
        ], [
            'foto.required' => 'Debe seleccionar una foto.',
            'foto.mimes'    => 'La foto debe ser una imagen JPG.',
            'foto.max'      => 'La foto no debe superar los 10 MB.',
        ]);
    
        try {
            $codiPers = trim($request->input('codi_pers'));
            $archivo  = $request->file('foto');
    
            // Nombre fijo: CODI_PERS.jpg
            $nameFile = $codiPers . '.jpg';
    
            // SOLO 'Fotos'. El script charge_file.php ya le concatena la IP y 'Biblioteca_Grafica'
            $ruta = 'Fotos';
    
            // Enviar la petición usando el formato nativo de Laravel para Multipart Form-Data
            $response = Http::withToken('457862h45hj7u5126h58d2s51s2s')
                ->attach('archivo', file_get_contents($archivo->getRealPath()), $nameFile)
                ->post('http://190.116.178.163/apps/api/file-control/charge_file.php', [
                    'nameFile' => $nameFile,
                    'ruta'     => $ruta,
                ]);
    
            // Validar la respuesta JSON del proxy
            $proxyData = $response->json();
    
            if ($response->failed() || (isset($proxyData['success']) && $proxyData['success'] === false)) {
                Log::error('uploadFotoPersonal: fallo en proxy', [
                    'codi_pers' => $codiPers,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'El servidor de archivos rechazó la imagen.',
                    'detalle' => $response->body(),
                ], 500);
            }
    
            Log::info('uploadFotoPersonal: foto subida correctamente', [
                'codi_pers' => $codiPers,
                'nameFile'  => $nameFile,
            ]);
    
            return response()->json([
                'success'  => true,
                'message'  => 'Foto guardada correctamente.',
                'foto_url' => "http://190.116.178.163/Biblioteca_Grafica/Fotos/{$nameFile}",
            ]);
    
        } catch (\Exception $e) {
            Log::error('uploadFotoPersonal error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al subir la foto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir DNI (anverso y/o reverso) — mismo flujo que la foto del personal:
     * Laravel → charge_file.php (IP pública) → acceso directo "Biblioteca Grafica" → 10.2
     * DNI1_1 = ANVERSO, DNI2_1 = REVERSO (carpetas donde el visor ya busca {CODI}.jpg)
     */
    public function uploadDniPersonal(Request $request)
    {
        $request->validate([
            'dni_anverso' => 'nullable|file|mimes:jpg,jpeg|max:10240',
            'dni_reverso' => 'nullable|file|mimes:jpg,jpeg|max:10240',
            'codi_pers'   => 'required|string',
        ], [
            'dni_anverso.mimes' => 'El DNI anverso debe ser una imagen JPG.',
            'dni_anverso.max'   => 'El DNI anverso no debe superar los 10 MB.',
            'dni_reverso.mimes' => 'El DNI reverso debe ser una imagen JPG.',
            'dni_reverso.max'   => 'El DNI reverso no debe superar los 10 MB.',
        ]);

        try {
            $codiPers = trim($request->input('codi_pers'));

            if (!$request->hasFile('dni_anverso') && !$request->hasFile('dni_reverso')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibió ninguna imagen del DNI.',
                ], 400);
            }

            // Nombre fijo: CODI_PERS.jpg (así lo muestra el visor existente)
            $nameFile = $codiPers . '.jpg';

            $pendientes = [];
            if ($request->hasFile('dni_anverso')) {
                $pendientes['anverso'] = ['file' => $request->file('dni_anverso'), 'ruta' => 'DNI1_1'];
            }
            if ($request->hasFile('dni_reverso')) {
                $pendientes['reverso'] = ['file' => $request->file('dni_reverso'), 'ruta' => 'DNI2_1'];
            }

            $fallos = [];
            foreach ($pendientes as $lado => $info) {
                $response = Http::withToken('457862h45hj7u5126h58d2s51s2s')
                    ->attach('archivo', file_get_contents($info['file']->getRealPath()), $nameFile)
                    ->post('http://190.116.178.163/apps/api/file-control/charge_file.php', [
                        'nameFile' => $nameFile,
                        'ruta'     => $info['ruta'],
                    ]);

                $proxyData = $response->json();
                if ($response->failed() || (isset($proxyData['success']) && $proxyData['success'] === false)) {
                    $fallos[] = $lado;
                    Log::error('uploadDniPersonal: fallo en proxy', [
                        'codi_pers' => $codiPers,
                        'lado'      => $lado,
                        'status'    => $response->status(),
                        'body'      => $response->body(),
                    ]);
                }
            }

            if ($fallos) {
                return response()->json([
                    'success' => false,
                    'message' => 'El servidor de archivos rechazó la imagen del DNI (' . implode(', ', $fallos) . ').',
                ], 500);
            }

            Log::info('uploadDniPersonal: DNI guardado', [
                'codi_pers' => $codiPers,
                'lados'     => array_keys($pendientes),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DNI guardado correctamente.',
                'lados'   => array_keys($pendientes),
            ]);

        } catch (\Exception $e) {
            Log::error('uploadDniPersonal error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al subir el DNI: ' . $e->getMessage(),
            ], 500);
        }
    }




    
    public function saveDeclaracionJurada(SaveDeclaracionJuradaRequest $request)
    {

        $djService = new DjService;

        $saved = $djService->guardarDeclaracionJurada($request->validated());

        return response()->json([
            'message' => 'Declaración Jurada guardada correctamente',
            'data' => $saved,
        ], 200);
    }

    private function getTelefonos($codiPers, $source = 'migracion')
    {
        // 1. Definir tabla según source
        $tablaTel = ($source === 'pendiente')
            ? 'si_solm.dbo.TELEFONO'
            : 'sisolm_web.dbo.sw_MIGRA_TELEFONO';

        // 2. Obtener teléfonos desde la tabla correspondiente
        $telefonos = DB::select(
            "SELECT * FROM {$tablaTel} WHERE CODI_PERS = ? AND (TELE_VIGENCIA = 'SI' OR TELE_VIGENCIA IS NULL)",
            [$codiPers]
        );

        $result = [
            'PERS_TELEFONO'      => null,
            'PERS_WHATSAPP'      => null,
            'PERS_NROEMERGENCIA' => null,
            'PERS_NOMCONTACTO'   => null,
            'PERS_EMERC_FAMILIAR'=> null,
        ];

        if (!empty($telefonos)) {
            foreach ($telefonos as $tel) {
                $tel = (array) $tel;

                // Emergencia:
                //  - '1'  → registro legado (TELE_EMERGENCIA siempre '0'/'1' en datos antiguos)
                //  - NULL → formato nuevo (solo el registro de emergencia guarda NULL;
                //           los teléfonos personal/whatsapp guardan '0')
                $esEmergencia = ($tel['TELE_EMERGENCIA'] ?? null) === null
                    || $tel['TELE_EMERGENCIA'] == '1';
                if ($esEmergencia) {
                    $result['PERS_NROEMERGENCIA']  = $tel['NRO_TELE']          ?? null;
                    $result['PERS_NOMCONTACTO']    = $tel['TELE_CONTACTO']     ?? null;
                    $result['PERS_EMERC_FAMILIAR'] = $tel['VINCULO_FAMILIAR']  ?? null;
                    continue;
                }

                // WSP y personal
                if ($tel['NRO_WSP'] == 1) {
                    $result['PERS_WHATSAPP'] = $tel['NRO_TELE'] ?? null;
                    // Si solo hay un registro con NRO_WSP=1 
                    // significa que personal == whatsapp
                    if (empty($result['PERS_TELEFONO'])) {
                        $result['PERS_TELEFONO'] = $tel['NRO_TELE'] ?? null;
                    }
                } else {
                    $result['PERS_TELEFONO'] = $tel['NRO_TELE'] ?? null;
                }
            }
        }

        return $result;
    }

    public function getPersonalData(Request $request)
    {
        try {
            $codiPers = $request->get('codi_pers') ?? session('USER_Codi_Pers');
            $source = $request->get('source', 'migracion'); // ✅ NUEVO parámetro

            // Si viene de PENDIENTES → usar directamente si_solm.dbo.PERSONAL
            if ($source === 'pendiente') {
                $personalData = DB::select(
                    'SELECT * FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
                    [$codiPers]
                );

                if (empty($personalData)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontraron datos del personal',
                    ], 404);
                }

                $data = (array) $personalData[0];
                $sourceTable = 'PERSONAL';

            } else {
                // ✅ Flujo original para MIGRACIÓN
                $djData = DB::select(
                    'SELECT * FROM si_solm.dbo.DJ2026_PERSONAL WHERE CODI_PERS = ?',
                    [$codiPers]
                );

                if (!empty($djData)) {
                    $data = (array) $djData[0];
                    $sourceTable = 'DJ2026_PERSONAL';
                } else {
                    $migraData = DB::select(
                        'SELECT * FROM sisolm_web.dbo.sw_MIGRA_PERSONAL WHERE CODI_PERS = ?',
                        [$codiPers]
                    );

                    if (empty($migraData)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No se encontraron datos del personal',
                        ], 404);
                    }

                    $data = (array) $migraData[0];
                    $sourceTable = 'sw_MIGRA_PERSONAL';
                }
            }

            // ✅ Completar campos NULL desde PERSONAL (solo si no viene ya de PERSONAL)
            if ($source !== 'pendiente') {
                $data = $this->completarCamposNull($data, $codiPers);
            }

            $telefonos = $this->getTelefonos($codiPers, $source);

            $data['PERS_TELEFONO']       = $telefonos['PERS_TELEFONO']       ?? $data['PERS_TELEFONO']       ?? null;
            $data['PERS_WHATSAPP']       = $telefonos['PERS_WHATSAPP']       ?? $data['PERS_WHATSAPP']       ?? null;
            $data['PERS_NROEMERGENCIA']  = $telefonos['PERS_NROEMERGENCIA']  ?? $data['PERS_NROEMERGENCIA']  ?? null;
            $data['PERS_NOMCONTACTO']    = $telefonos['PERS_NOMCONTACTO']    ?? $data['PERS_NOMCONTACTO']    ?? null;
            $data['PERS_EMERC_FAMILIAR'] = $telefonos['PERS_EMERC_FAMILIAR'] ?? $data['PERS_EMERC_FAMILIAR'] ?? null;


            $dni = $data['NRO_DOCU_IDEN'] ?? '';

            // Familiares — pendiente usa DERECHO_HABIENTE directamente
            $familiaresTable = ($sourceTable === 'DJ2026_PERSONAL')
                ? 'si_solm.dbo.DERECHO_HABIENTE'
                : ($source === 'pendiente'
                    ? 'si_solm.dbo.DERECHO_HABIENTE'
                    : 'sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE');

            $filtroVigenciaFamiliares = $familiaresTable === 'si_solm.dbo.DERECHO_HABIENTE'
                ? " AND UPPER(LTRIM(RTRIM(ISNULL(DEHA_VIGENCIA, 'SI')))) = 'SI'"
                : '';
            $familiares = DB::select(
                "SELECT *, 
                (ISNULL(APEL_1,'') + ' ' + ISNULL(APEL_2,'') + ' ' + ISNULL(NOMB_1,'') + ' ' + ISNULL(NOMB_2,'')) AS Nombres,
                CONVERT(CHAR(10), FECH_NACI, 105) AS FECH_NACI,
                FECH_NACI AS FECH_NACI2
                FROM {$familiaresTable}
                WHERE CODI_PERS = ?
                {$filtroVigenciaFamiliares}
                ORDER BY TIPO_RELA",
                [$codiPers]
            );

            // 5. Obtener OCUPACIONES ALTERNAS
            $ocupacionesTable = ($sourceTable === 'DJ2026_PERSONAL')
                ? 'si_solm.dbo.DJ2026_OCUPACIONES_PER'
                : 'sisolm_web.dbo.dj2026_OCUPACIONES';

            $ocupaciones = DB::select(
                "SELECT dj2026_descripcion FROM {$ocupacionesTable} WHERE PERS_DNI = ?",
                [$dni]
            );

      
     
            

            // 6. Formatear fechas para HTML inputs
            $data = $this->formatDatesForInput($data);
            

            

            // 7. Agregar foto path
            $data['FOTO_PATH'] = "http://190.116.178.163/Biblioteca_Grafica/Fotos/{$codiPers}.jpg";

            return response()->json([
                'success' => true,
                'data' => $data,
                'familiares' => $this->groupFamiliares($familiares),
                'ocupaciones_alternas' => array_map(function ($o) {
                    return (array) $o;
                }, $ocupaciones),
                'source_table' => $sourceTable,
                'is_migrado' => ($sourceTable === 'DJ2026_PERSONAL'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getPersonalData: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Completar campos NULL desde si_solm.dbo.PERSONAL
     */
    private function completarCamposNull($data, $codiPers)
    {
        // Buscar datos en la tabla PERSONAL
        $personalData = DB::select(
            'SELECT * FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        // Si no existe en PERSONAL, retornar data sin cambios
        if (empty($personalData)) {
            return $data;
        }

        $personal = (array) $personalData[0];

        // Lista de campos que existen en ambas tablas y pueden completarse
        $camposComunes = [
            'NRO_DOCU_IDEN',
            'APEL_1',
            'APEL_2',
            'NOMB_1',
            'NOMB_2',
            'CODI_SIST_PENS',
            'FECH_INGRE',
            'FECH_NACI',
            'ESSALUD',
            'ESTA_CIVI',
            'SEXO',
            'NACIONALIDAD',
            'DIRECCION',
            'PERS_EMAIL',
            'PERS_CONBREVETE',
            'PERS_BREVETE',
            'FECH_CESE',
            'PERS_FECHCADUCADNI',
            'ESCI_CODIGO',
            'tipo_sangr',
            'peso_kilo',
            'tall_metr',
            'PERS_SNADAR',
            'PERS_CONSMO',
            'PERS_LUGARSMO',
            'PERS_CONDISCAMEC',
            'PERS_NRODISCAMEC',
            'PERS_SMO',
            'PERS_CONLICARMAS',
            'PERS_TIPOARMA',
            'PERS_CONARMAS',
            'CLASE_BREVETE',
            'PERS_TIPO_VEHICULO',
            'PERS_VEHICULO_PROPIO',
            'PERS_NOMCONTACTO',
            'PERS_NROEMERGENCIA',
            //'PERS_CONYUGE',
            'PERS_EMERC_FAMILIAR',
            'PERS_CTRABANT',
            'PERS_CARGOTRABANT',
            'PERS_DURACIONANT',
            'PERS_TELEFONO',
            'PERS_WHATSAPP',
            'PERS_EMBARGO',
            'PERS_PENSIONISTA',
            'PERS_GRADO_INSTRUCCION',
            'CARR_CODIGO',
            'IEDU_CODIGO',
            'EGRESO_EDUCATIVO',
            'PERS_DEPT_ACT',
            'PERS_PROV_ACT',
            'PERS_DIST_ACT',
            'PERS_DPTO_DIRDNI',
            'PERS_PROV_DIRDNI',
            'PERS_DIST_DIRDNI',
            'PERS_DIREC_DNI',
            'PERS_SEXO',
            'dj2026_laboral_1',
            'dj2026_laboral_2',
        ];

        // Completar campos NULL
        foreach ($camposComunes as $campo) {
            // Si el campo está NULL o vacío en $data, pero existe en $personal
            if (
                (is_null($data[$campo] ?? null) || empty($data[$campo]))
                && isset($personal[$campo])
                && !is_null($personal[$campo])
                && !empty($personal[$campo])
            ) {

                $data[$campo] = $personal[$campo];
            }
        }

        return $data;
    }

    /**
     * Obtener catálogos (grados, carreras, instituciones, etc.)
     */
    public function getCatalogs()
    {
        try {
            $grados = DB::select(
                'SELECT NIED_CODIGO AS id, NIED_ABREVIADO AS text 
                FROM si_solm.dbo.SUNAT_NIVEL_EDUCATIVO 
                ORDER BY NIED_DESCRIPCION'
            );

            $carreras = DB::select(
                'SELECT CARR_CODIGO AS id, CARR_DESCRIPCION AS text, IEDU_CODIGO 
                FROM si_solm.dbo.SUNAT_CARRERAS 
                ORDER BY CARR_DESCRIPCION'
            );

            $instituciones = DB::select(
                "SELECT IEDU_CODIGO AS id, IEDU_DESCRIPCION AS text 
                FROM si_solm.dbo.SUNAT_IEDUCATIVA 
                ORDER BY IEDU_DESCRIPCION"
            );

            $sistemasPrev = DB::select(
                "SELECT 
                    CODI_SIST_PENS AS id, 
                    CASE
                        WHEN CODI_SIST_PENS = '01' THEN 'ONP' ELSE DESC_SIST_PENS
                    END AS text
                FROM si_solm.dbo.SISTEMA_PENSIONES
                WHERE SIPE_VIGENCIA = 'SI'
                ORDER BY 
                    CASE CODI_SIST_PENS
                        WHEN '03' THEN 1 -- AFP PROFUTURO
                        WHEN '01' THEN 2 -- ONP
                        WHEN '02' THEN 3 -- AFP INTEGRA
                        WHEN '10' THEN 4 -- AFP PRIMA
                        WHEN '27' THEN 5 -- AFP HABITAT
                        WHEN '07' THEN 6 -- NO APORTACION
                        ELSE 99
                    END;"
            );

            $sangre = [
                ['id' => 'O+', 'text' => 'O+'],
                ['id' => 'O-', 'text' => 'O-'],
                ['id' => 'A+', 'text' => 'A+'],
                ['id' => 'A-', 'text' => 'A-'],
                ['id' => 'B+', 'text' => 'B+'],
                ['id' => 'B-', 'text' => 'B-'],
                ['id' => 'AB+', 'text' => 'AB+'],
                ['id' => 'AB-', 'text' => 'AB-'],
            ];

            $estadosCiviles = DB::select(
                "SELECT ESCI_CODIGO AS id, ESCI_DESCRIPCION AS text 
                FROM si_solm.dbo.ADMI_ESTADO_CIVIL 
                WHERE ESCI_VIGENCIA='SI'"
            );

            $tiposArma = DB::select(
                "SELECT CODI_TIPO AS id, DESC_TIPO AS text 
                FROM si_solm.dbo.ARMA_TIPO 
                WHERE VIGENCIA_TIPO='SI' 
                ORDER BY DESC_TIPO"
            );

            return response()->json([
                'success' => true,
                'grados' => $grados,
                'carreras' => $carreras,
                'instituciones' => $instituciones,
                'sangre' => $sangre,
                'estados_civiles' => $estadosCiviles,
                'tipos_arma' => $tiposArma,
                'sistemas_previsionales' => $sistemasPrev, 
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getCatalogs: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar catálogos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener países desde ADMI_PAIS
     */
    public function getPaises()
    {
        try {
            $paises = DB::select(
                "SELECT PAIS_CODIGO AS id, PAIS_DESCRIPCION AS text
                FROM si_solm.dbo.ADMI_PAIS
                ORDER BY PAIS_DESCRIPCION"
            );

            return response()->json([
                'success' => true,
                'paises' => $paises,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getPaises: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar países: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener ubicaciones (departamentos, provincias, distritos)
     */
    public function getUbicacion(Request $request)
    {
        try {
            $type = $request->get('type');
            $dept = $request->get('dept', '');
            $prov = $request->get('prov', '');

            $result = [];

            switch ($type) {
                case 'dept':
                    $result = DB::select(
                        "SELECT DEPA_CODIGO AS id, DEPA_DESCRIPCION AS text 
                        FROM si_solm.dbo.ADMI_DEPARTAMENTO 
                        WHERE DEPA_VIGENCIA='SI' 
                        ORDER BY DEPA_DESCRIPCION"
                    );
                    break;

                case 'prov':
                    $result = DB::select(
                        "SELECT PROVI_CODIGO AS id, PROVI_DESCRIPCION AS text 
                        FROM si_solm.dbo.ADMI_PROVINCIA 
                        WHERE DEPA_CODIGO = ? AND PROVI_VIGENCIA='SI' 
                        ORDER BY PROVI_DESCRIPCION",
                        [$dept]
                    );
                    break;

                case 'dist':
                    $result = DB::select(
                        "SELECT DIST_CODIGO AS id, DIST_DESCRIPCION AS text 
                        FROM si_solm.dbo.ADMI_DISTRITO 
                        WHERE PROVI_CODIGO = ? AND DIST_VIGENCIA='SI' 
                        ORDER BY DIST_DESCRIPCION",
                        [$prov]
                    );
                    break;
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Error en getUbicacion: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ubicación',
            ], 500);
        }
    }

    /**
     * GUARDAR Y MIGRAR - Proceso completo
     */
    public function saveDjCompleto(Request $request)
    {
        DB::beginTransaction();

        try {
            // ✅ Obtener código de personal
            $codiPers = $request->input('cod_postulante') ?? session('USER_Codi_Pers');

            if (empty($codiPers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo identificar el código de personal',
                ], 400);
            }

            // ✅ Obtener DNI
            $dni = $request->input('dni');
            if (empty($dni)) {
                $personalData = DB::select(
                    'SELECT NRO_DOCU_IDEN FROM sisolm_web.dbo.sw_MIGRA_PERSONAL WHERE CODI_PERS = ?',
                    [$codiPers]
                );
                $dni = !empty($personalData) ? $personalData[0]->NRO_DOCU_IDEN : null;
            }

            if (empty($dni)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el DNI',
                ], 400);
            }

            $data = $request->all();

            $errorDniHijos = $this->validarDniHijosRequeridos($request, $data, (string) $codiPers, true);
            if ($errorDniHijos) {
                DB::rollBack();
                return response()->json($errorDniHijos, 422);
            }

            $exentoValidaciones = in_array('exento_validaciones', session('funcionalidades', []));

            // ── Documentos obligatorios: solo se exige lo que falte en el servidor ──
            // Si el servidor de archivos no responde NO se bloquea el guardado.
            // Los usuarios con "exento_validaciones" no están obligados a adjuntarlos.
            $fotoReq       = $request->file('foto');
            $dniAnversoReq = $request->file('dni_anverso');
            $dniReversoReq = $request->file('dni_reverso');

            if (!$exentoValidaciones) {
                $estadoDni     = $this->dniPersonalEnServidor((string) $codiPers);
                $estadoFoto    = $this->fotoPersonalEnServidor((string) $codiPers);

                $faltaFoto = ($estadoFoto === false && !$fotoReq);

                $faltanLados = [];
                if ($estadoDni['consultado']) {
                    if (!$estadoDni['anverso'] && !$dniAnversoReq) $faltanLados[] = 'anverso';
                    if (!$estadoDni['reverso'] && !$dniReversoReq) $faltanLados[] = 'reverso';
                }

                $faltanPartes = [];
                if ($faltaFoto)                  $faltanPartes[] = 'foto del personal';
                if (!empty($faltanLados))        $faltanPartes[] = 'foto del DNI';

                if (!empty($faltanPartes)) {
                    DB::rollBack();
                    return response()->json([
                        'success'        => false,
                        'code'           => $faltaFoto ? 'falta_foto' : 'falta_dni',
                        'message'        => 'Falta ingresar ' . implode(' y ', $faltanPartes) . '.',
                        'falta_foto'     => $faltaFoto,
                        'falta_anverso'  => in_array('anverso', $faltanLados, true),
                        'falta_reverso'  => in_array('reverso', $faltanLados, true),
                    ], 422);
                }
            }

            foreach ([$fotoReq, $dniAnversoReq, $dniReversoReq] as $archivo) {
                if (!$archivo) {
                    continue;
                }
                $extension = strtolower((string) $archivo->getClientOriginalExtension());
                if (!in_array($extension, ['jpg', 'jpeg'], true)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'code'    => 'dni_invalido',
                        'message' => 'La foto del DNI debe ser una imagen JPG.',
                    ], 422);
                }
                if ($archivo->getSize() > 10 * 1024 * 1024) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'code'    => 'dni_invalido',
                        'message' => 'Cada foto del DNI no debe superar los 10 MB.',
                    ], 422);
                }
            }

            // Relacionar la sucursal seleccionada (SUCU_CODIGO) con la unidad operativa
            // UNID_OPER.UBICACION = SISO_SUCURSAL.SUCU_CODIGO → CODI_UNID_OPER
            $sucursalSel = strtoupper(trim($data['sucursal'] ?? ''));
            if ($sucursalSel !== '') {
                $data['SUCU_CODIGO'] = $sucursalSel;
                $uo = DB::selectOne(
                    'SELECT TOP 1 CODI_UNID_OPER FROM si_solm.dbo.UNID_OPER WHERE UBICACION = ?',
                    [$sucursalSel]
                );
                $data['CODI_UNID_OPER'] = $uo->CODI_UNID_OPER ?? null;
            }

            $tipoPer = trim($data['tipo_personal'] ?? '');
            if ($tipoPer === '') {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Debe seleccionar el tipo de trabajador.'], 422);
            }

            $tipoValido = DB::selectOne(
                'SELECT 1 FROM si_solm.dbo.ADMI_TIPO_PERSONAL WHERE TIPE_CODIGO = ?',
                [$tipoPer]
            );
            if (!$tipoValido) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'El tipo de trabajador seleccionado no es válido.'], 422);
            }

            // Solo usuarios autorizados pueden cambiar el tipo de personal.
            $tipoActualRow = DB::selectOne(
                'SELECT PERS_TIPOTRAB FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
                [$codiPers]
            );
            $tipoActual = trim((string)($tipoActualRow->PERS_TIPOTRAB ?? ''));

            if ($tipoPer !== $tipoActual) {
                $usuarioTipoPer = strtoupper(trim((string)(session('usuario') ?? '')));
                if (!in_array('cambiar_tipo_personal', session('funcionalidades', []))) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'No tiene permisos para modificar el tipo de personal.'], 403);
                }
            }

            if (!$this->validarCambioTipoPersonal($tipoActual, $tipoPer, in_array('cambiar_tipo_personal', session('funcionalidades', [])))) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'No está permitido ese cambio de tipo de personal.'], 422);
            }
            // En este flujo se actualiza la DJ de un personal ya existente.
            // La restricción de rango de edad se aplica al crear personal en
            // saveNuevaDj; no debe impedir actualizar su DJ ni la caducidad del DNI.
            $fechaNacimiento = trim((string) ($data['fecha_nacimiento'] ?? ''));
            $fechaNacimientoCarbon = $this->parsearFechaNacimiento($fechaNacimiento);

            if (!$fechaNacimientoCarbon) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'La fecha de nacimiento es obligatoria y debe tener formato válido.'], 422);
            }

            $fechaNacimiento = $fechaNacimientoCarbon->format('Y-m-d');

            if ($fechaNacimientoCarbon->isToday() || $fechaNacimientoCarbon->isFuture()) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'La fecha de nacimiento debe ser anterior a hoy.'], 422);
            }

            $source = $request->input('source', 'migracion');

            // ✅ 1. SOLO MARCAR COMO MIGRADO en sw_MIGRA_PERSONAL (NO actualizar otros campos)
            DB::update(
                'UPDATE sisolm_web.dbo.sw_MIGRA_PERSONAL 
            SET SIP_migrado = 1, SIP_fechaModifcacion = GETDATE()
            WHERE CODI_PERS = ?',
                [$codiPers]
            );

            // ✅ 1.5. NOTIFICACIÓN SIP ATENDIDA ──────────────────────────────
            // Cuando una solicitud llega desde el SIP, aparece en el popup y en
            // la campanita para todo el equipo. Aquí se archiva sola en cuanto
            // alguien la valida y guarda desde ACTUALIZAR DJ.
            //
            // `origen` lo envía únicamente actualizar_dj.js, así que sirve para
            // distinguir pantallas: una edición desde Gestión DJ NO archiva.
            // Se acepta también `cambios` como respaldo para los navegadores que
            // todavía tengan en caché el bundle JS anterior (sin el flag).
            if (($data['origen'] ?? '') === 'actualizar_dj' || array_key_exists('cambios', $data)) {
                $notifsAtendidas = NotificacionModel::archivarNotificacionesDePersonal($codiPers);
                if ($notifsAtendidas > 0) {
                    Log::info('NotificacionSIP: archivada automáticamente al validar en Actualizar DJ', [
                        'CODI_PERS'     => $codiPers,
                        'notificaciones' => $notifsAtendidas,
                        'usuario'       => session('usuario'),
                    ]);
                }
            }

            // ✅ 2. INSERTAR/ACTUALIZAR DIRECTAMENTE en DJ2026_PERSONAL
            // La fecha de ingreso solo se establece al registrar o recontratar.
            // En una DJ existente se preserva sin aceptar cambios del formulario.
            unset($data['FECH_INGRE'], $data['FECH_INGRE_PLANILLA'], $data['fecha_ingreso_solmar']);
            unset($data['FECH_CESE'], $data['fecha_cese']);
            // Cargo: solo el rol ADMINS RRHH (tipo_rol 17) puede modificarlo;
            // los demás roles conservan el CODI_CARG actual (el select viene bloqueado en UI)
            if (session('tipo_rol') != 17) {
                unset($data['cargo']);
            }
            // SCTR: OP (01/03) → 'SI' automático; ADMIN (02/05) → según checkbox
            $this->aplicarScrt($codiPers, $data);
            $asigFamiAnterior = DB::selectOne(
                'SELECT ASIG_FAMI FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
                [$codiPers]
            );
            $resultadoAsigFami = $this->aplicarAsigFami($codiPers, $data, (string) ($asigFamiAnterior->ASIG_FAMI ?? ''));
            $this->aplicarConHijos($codiPers, $data);
            $fechasIngresoProtegidas = ['FECH_INGRE'];
            $this->insertOrUpdateDJ2026Personal($codiPers, $data, $source, $fechasIngresoProtegidas);

            // ✅ 2.5. SINCRONIZAR DJ2026_PERSONAL → PERSONAL (solo columnas con valor NO NULL)
            $this->syncDJ2026ToPersonal($codiPers, $fechasIngresoProtegidas);

            // ✅ 3. GUARDAR FAMILIARES en tablas temporales
            $this->saveFamiliaresTemp($codiPers, $data);

            // ✅ 4. Sincronizar familiares existentes usando baja lógica
            $this->migrarFamiliaresConVigencia($codiPers, $data);
            $this->asegurarFechasNacimientoHijosGuardadas($codiPers, $data);

            // ✅ 5. GUARDAR OCUPACIONES en tabla temporal
            //$this->saveOcupacionesTemp($dni, $data);

            // ✅ 6. COPIAR OCUPACIONES a DJ2026_OCUPACIONES_PER
            //$this->migrarOcupaciones($dni);

            // ✅ 6. GUARDAR TELÉFONOS EN sw_MIGRA_TELEFONO
            $this->saveTelefonosTemp($codiPers, $data);

            // ✅ 7. MIGRAR TELÉFONOS → si_solm.dbo.TELEFONO
            $this->migrarTelefonos($codiPers);

            // Auditoria de excepción de edad (si la hubo en esta edición)
            if (!empty($excepcionValidaEdad)) {
                $this->registrarExcepcionEdad(
                    (string) $codiPers,
                    (string) $dni,
                    $fechaNacimiento,
                    (int) $edad,
                    $reglasEdad,
                    $data,
                    $tipoPer,
                    $sucursalSel
                );
            }

            // ── Subir foto y DNI al servidor de archivos ──────────────────
            // Solo se sube lo adjuntado. Si el servidor de archivos falla NO se
            // bloquea el guardado: se avisa qué quedó pendiente.
            $subidaFoto    = $this->subirFotoAlServidor((string) $codiPers, $fotoReq);
            $subidaDni     = $this->subirDniAlServidor((string) $codiPers, $dniAnversoReq, $dniReversoReq);
            $fotoPendiente = !$subidaFoto['ok'];
            $dniPendiente  = !$subidaDni['ok'];

            DB::commit();

            // === INICIO: ENVÍO DE CORREO AUTOMÁTICO ===
            \Illuminate\Support\Facades\Log::info("=== 1. INICIANDO PROCESO DE CORREO ===");
            try {
                // 1. Limpiamos espacios que rompen la validación silenciosamente
                $correoDestino = isset($data['correo']) ? trim($data['correo']) : null;
                \Illuminate\Support\Facades\Log::info("=== 2. Correo destino detectado: " . ($correoDestino ?: 'VACIÓ O NULO') . " ===");
                
                if (!empty($correoDestino) && filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
                    \Illuminate\Support\Facades\Log::info("=== 3. Correo válido. Intentando enviar de forma SÍNCRONA (send)... ===");
                    
                    $nombrePersonal = trim(($data['nombre1'] ?? '') . ' ' . ($data['apellido_paterno'] ?? '') . ' ' . ($data['apellido_materno'] ?? ''));
                    if (empty($nombrePersonal)) {
                        $nombrePersonal = $data['nombres_apellidos'] ?? 'Personal';
                    }

                    $datosCorreo = [
                        'nombre'  => mb_strtoupper($nombrePersonal, 'UTF-8'),
                        'dni'     => $dni,
                        'fecha'   => date('d/m/Y h:i A'),
                        'cambios' => $data['cambios'] ?? [] // 🔥 AQUÍ RECIBIMOS EL ARRAY DEL JS
                    ];

                    // Usamos send() forzosamente para asegurar el envío en pruebas
                    \Illuminate\Support\Facades\Mail::to($correoDestino)->send(new \App\Mail\DjAprobadaMail($datosCorreo));
                    
                    \Illuminate\Support\Facades\Log::info("=== 4. ¡ÉXITO! El servidor SMTP aceptó y envió el correo. ===");
                } else {
                    \Illuminate\Support\Facades\Log::warning("=== 3. ABORTADO: No se envió correo DJ. Email vacío o inválido: '{$correoDestino}' ===");
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('=== 4. ERROR CRÍTICO SMTP === : ' . $e->getMessage());
            }
            \Illuminate\Support\Facades\Log::info("=== 5. FIN PROCESO DE CORREO ===");
            // === FIN: ENVÍO DE CORREO AUTOMÁTICO ===

            $pendientesMsg = [];
            if ($fotoPendiente) $pendientesMsg[] = 'la foto del personal';
            if ($dniPendiente)  $pendientesMsg[] = 'el DNI';

            return response()->json([
                'success'        => true,
                'asig_fami_desactivada' => $resultadoAsigFami['desactivada_por_mayoria_edad'],
                'message'        => !empty($pendientesMsg)
                    ? 'Declaración Jurada guardada y migrada correctamente, pero ' . implode(' y ', $pendientesMsg) . ' quedó pendiente de subir.'
                    : 'Declaración Jurada guardada y migrada correctamente',
                'foto_pendiente' => $fotoPendiente,
                'dni_pendiente'  => $dniPendiente,
                'dni_faltan'     => $subidaDni['faltan'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en saveDjCompleto: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getBackupData(Request $request)
    {
        try {
            $codiPers = $request->get('codi_pers');

            if (empty($codiPers)) {
                return response()->json(['success' => false, 'message' => 'Código requerido'], 400);
            }

           $backup = DB::select(
                'SELECT per.*, CO.ESCI_DESCRIPCION, SS.DESC_SIST_PENS, edu.NIED_ABREVIADO 
                FROM si_solm.dbo.DJ2026_BACKUP_PERSONAL as per
                LEFT JOIN si_solm.dbo.ADMI_ESTADO_CIVIL AS CO ON CO.ESCI_CODIGO = per.ESCI_CODIGO
                LEFT JOIN si_solm.dbo.SISTEMA_PENSIONES AS SS ON SS.CODI_SIST_PENS = per.CODI_SIST_PENS
                LEFT JOIN si_solm.dbo.SUNAT_NIVEL_EDUCATIVO AS EDU ON EDU.NIED_CODIGO = per.PERS_GRADO_INSTRUCCION 
                WHERE per.CODI_PERS = ?',
                [$codiPers]
            );

            if (empty($backup)) {
                $backup = DB::select(
                    'SELECT per.*, CO.ESCI_DESCRIPCION, SS.DESC_SIST_PENS, edu.NIED_ABREVIADO 
                    FROM si_solm.dbo.PERSONAL as per
                    LEFT JOIN si_solm.dbo.ADMI_ESTADO_CIVIL AS CO ON CO.ESCI_CODIGO = per.ESCI_CODIGO
                    LEFT JOIN si_solm.dbo.SISTEMA_PENSIONES AS SS ON SS.CODI_SIST_PENS = per.CODI_SIST_PENS
                    LEFT JOIN si_solm.dbo.SUNAT_NIVEL_EDUCATIVO AS EDU ON EDU.NIED_CODIGO = per.PERS_GRADO_INSTRUCCION 
                    WHERE per.CODI_PERS = ?',
                    [$codiPers]
                );
            }

            if (empty($backup)) {
                return response()->json(['success' => false, 'message' => 'Sin registro previo']);
            }

            $data = $this->formatDatesForInput((array) $backup[0]);
            // ✅ Alias: el panel backup espera 'dj2026_ciudad_naci' pero la BD guarda en NACIONALIDAD
            if (!empty($data) && !isset($data['dj2026_ciudad_naci'])) {
                $data['dj2026_ciudad_naci'] = $data['NACIONALIDAD'] ?? null;
            }

            // ✅ FAMILIARES DEL BACKUP
            $familiares = DB::select(
                "SELECT
                TIPO_RELA,
                ISNULL(APEL_1,'') + ' ' + ISNULL(APEL_2,'') + ' ' +
                ISNULL(NOMB_1,'') + ' ' + ISNULL(NOMB_2,'') AS Nombres,
                CONVERT(CHAR(10), FECH_NACI, 103) AS FECH_NACI
             FROM si_solm.dbo.DERECHO_HABIENTE
             WHERE CODI_PERS = ?
               AND UPPER(LTRIM(RTRIM(ISNULL(DEHA_VIGENCIA, 'SI')))) = 'SI'
             ORDER BY TIPO_RELA",
                [$codiPers]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
                'familiares' => $familiares,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getBackupData: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ✅ FAMILIARES - Guardar en tabla temporal
    private function saveFamiliaresTemp($codiPers, $data)
    {
        $listadoFamiliarEnviado = !empty($data['FAM_PRESENTE']);
        if ((!isset($data['FAM_NOMBRES']) || !is_array($data['FAM_NOMBRES'])) && !$listadoFamiliarEnviado) {
            Log::info('No hay familiares para guardar');

            return;
        }

        $nombresFamiliares = is_array($data['FAM_NOMBRES'] ?? null) ? $data['FAM_NOMBRES'] : [];

        // Limpiar registros previos
        DB::delete(
            'DELETE FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE WHERE CODI_PERS = ?',
            [$codiPers]
        );

        // Insertar nuevos
        foreach ($nombresFamiliares as $index => $nombreCompleto) {
            if (empty($nombreCompleto)) {
                continue;
            }

            $parentesco = strtoupper(trim($data['FAM_PARENTESCO'][$index] ?? ''));
            // En Datos Familiares solo se registra fecha de nacimiento para hijos.
            $fechaNaci = preg_match('/^HIJ/i', $parentesco)
                ? ($data['FAM_FECHA_NACI'][$index] ?? null)
                : null;

            if (! preg_match('/^[\p{L}\p{M}]+(?:[\s.,\'’-]+[\p{L}\p{M}]+)*$/u', trim($nombreCompleto))) {
                throw new \InvalidArgumentException('Revisa el nombre del familiar: usa letras y separadores válidos.');
            }

            // Split del nombre
            $nombreCompleto = trim($nombreCompleto);

            if (strpos($nombreCompleto, ',') !== false) {
                [$apellidos, $nombres] = explode(',', $nombreCompleto, 2);
                $apellidos = trim($apellidos);
                $nombres = trim($nombres);

                $apellidosParts = preg_split('/\s+/', $apellidos, 2);
                $nombresParts = preg_split('/\s+/', $nombres, 2);

                $apel1 = $apellidosParts[0] ?? '';
                $apel2 = $apellidosParts[1] ?? '';
                $nomb1 = $nombresParts[0] ?? '';
                $nomb2 = $nombresParts[1] ?? '';
            } else {
                $nameParts = preg_split('/\s+/', $nombreCompleto);
                $apel1 = $nameParts[0] ?? '';
                $apel2 = $nameParts[1] ?? '';
                $nomb1 = $nameParts[2] ?? '';
                $nomb2 = isset($nameParts[3]) ? implode(' ', array_slice($nameParts, 3)) : '';
            }

            DB::insert(
                'INSERT INTO sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE 
            (CODI_PERS, TIPO_RELA, APEL_1, APEL_2, NOMB_1, NOMB_2, FECH_NACI) 
            VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$codiPers, $parentesco, $apel1, $apel2, $nomb1, $nomb2, $fechaNaci]
            );
        }
    }

    // ✅ OCUPACIONES - Guardar en tabla temporal
    private function saveOcupacionesTemp($dni, $data)
    {
        if (!isset($data['dj2026_descripcion']) || !is_array($data['dj2026_descripcion'])) {
            Log::info('No hay ocupaciones para guardar');

            return;
        }

        // Limpiar previas
        DB::delete(
            'DELETE FROM sisolm_web.dbo.dj2026_OCUPACIONES WHERE PERS_DNI = ?',
            [$dni]
        );

        // Insertar nuevas (máximo 2)
        $ocupaciones = array_filter($data['dj2026_descripcion']);
        $ocupaciones = array_slice($ocupaciones, 0, 2);

        foreach ($ocupaciones as $desc) {
            DB::insert(
                'INSERT INTO sisolm_web.dbo.dj2026_OCUPACIONES (PERS_DNI, dj2026_descripcion) VALUES (?, ?)',
                [$dni, strtoupper(trim($desc))]
            );
        }
    }

    private function insertOrUpdateDJ2026Personal($codiPers, $data, $source = 'migracion', array $camposProtegidos = [])
    {
        // ✅ 1. Obtener datos base de sw_MIGRA_PERSONAL
        $migraData = DB::select(
            'SELECT * FROM sisolm_web.dbo.sw_MIGRA_PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        // Se elimina la excepción para permitir guardar a los no verificados
        $base = !empty($migraData) ? (array) $migraData[0] : [];

        // ✅ 2. Obtener datos de si_solm.dbo.PERSONAL (TABLA ORIGINAL/MAESTRA)
        $personalOriginal = DB::select(
            'SELECT * FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        $original = !empty($personalOriginal) ? (array) $personalOriginal[0] : [];

        // ✅ 3. Definir campos por tipo
        $datetimeFields = [
            'FECH_INGRE',
            'FECH_NACI',
            'FECH_CESE',
            'PERS_FECHEMISIONDNI',
            'PERS_FECHCADUCADNI',
            'VCMTO',
            'FECH_INIC_AFIL',
            'PERS_FECHAREG',
            'USUA_FECHA_REG',
            'USUA_FECHA_MOD',
            'PERS_FECHARETIRO',
            'Pers_fech_venc_lic_arm',
            'PERS_VERIF_FECHA',
            'FEC_MOD_PLAN',
            'FECH_EXP_BREVETE',
            'FECH_REVAL_BREVETE',
            'SIP_fechaCreacion',
            'SIP_fechaModifcacion',
        ];

        $bitFields = [
            'ESTA_ACTI',
            'COMI_PESC',
            'AFIL_SIND',
            'CARG_FAMI',
            'SEGU_VIDA_LEY',
            'FOTO_SI_NO',
            'GRAT',
            'VACA',
            'AFECTO_LEY',
            'PERS_FLAG',
            'apor_essa',
            'apor_sena',
            'apor_senc',
            'apor_cona',
            'MIGRADO',
            'SIP_habilitado',
            'SIP_migrado',
            'SIP_activo',
            'NO_CADUCA_DNI',
        ];

        $numericFields = [
            'SUEL_BASI',
            'SUEL_NETO',
            'MONT_PAGO_COME',
            'HORA_LABO',
            'UTIL03',
            'HORA_AUTO',
            'peso_kilo',
            'tall_metr',
            'EGRESO_EDUCATIVO',
            'dj2026_cantprofesion',
            'dj2026_experiencia_anios',
            'dj2026_experiencia_meses',
            'fotocheck',
            'horario',
            'CODI_CATE_TRAB',
        ];

        // ✅ 4. Función helper mejorada con limpieza y validación
        $getValue = function ($formKey, $baseKey = null, $originalKey = null) use ($data, $base, $original, $datetimeFields, $bitFields, $numericFields) {
            $baseKey = $baseKey ?? $formKey;
            $originalKey = $originalKey ?? $baseKey;

            $rawValue = null;

            // 1. Intentar desde el formulario
            if (isset($data[$formKey]) && $data[$formKey] !== '' && $data[$formKey] !== null) {
                $rawValue = $data[$formKey];
            }
            // 2. Intentar desde base (sw_MIGRA_PERSONAL)
            elseif (isset($base[$baseKey]) && $base[$baseKey] !== '' && $base[$baseKey] !== null) {
                $rawValue = $base[$baseKey];
            }
            // 3. Intentar desde original (si_solm.dbo.PERSONAL) ✅ AQUÍ BUSCAMOS EN LA TABLA MAESTRA
            elseif (isset($original[$originalKey]) && $original[$originalKey] !== '' && $original[$originalKey] !== null) {
                $rawValue = $original[$originalKey];
            }

            // ✅ 4. LIMPIAR Y VALIDAR SEGÚN EL TIPO DE CAMPO

            // Si es un campo DATETIME
            if (in_array($baseKey, $datetimeFields, true)) {
                if (
                    is_null($rawValue) ||
                    $rawValue === '' ||
                    $rawValue === '0000-00-00' ||
                    $rawValue === '0000-00-00 00:00:00'
                ) {
                    return null;
                }

                if ($rawValue instanceof \DateTimeInterface) {
                    return $rawValue->format('Y-m-d\TH:i:s.v');
                }

                if (is_string($rawValue)) {
                    $rawValue = trim($rawValue);

                    // YYYY-MM-DD
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawValue)) {
                        return $rawValue . 'T00:00:00.000';
                    }

                    // DD/MM/YYYY
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawValue)) {
                        [$d, $m, $y] = explode('/', $rawValue);

                        return "{$y}-{$m}-{$d}T00:00:00.000";
                    }

                    // YYYY-MM-DD HH:MM:SS
                    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $rawValue)) {
                        return str_replace(' ', 'T', $rawValue) . '.000';
                    }

                    // YYYY-MM-DD HH:MM:SS.mmm
                    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3}$/', $rawValue)) {
                        return str_replace(' ', 'T', $rawValue);
                    }

                    // YYYY-MM-DDTHH:MM:SS
                    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $rawValue)) {
                        return $rawValue . '.000';
                    }

                    // YYYY-MM-DDTHH:MM:SS.mmm
                    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}$/', $rawValue)) {
                        return $rawValue;
                    }
                }

                return null;
            }

            // Si es un campo BIT
            if (in_array($baseKey, $bitFields)) {
                if (is_null($rawValue) || $rawValue === '') {
                    return 0;
                }
                if ($rawValue === true || $rawValue === 1 || $rawValue === '1' || strtoupper($rawValue) === 'SI') {
                    return 1;
                }

                return 0;
            }

            // Si es un campo NUMERIC
            if (in_array($baseKey, $numericFields)) {
                if (is_null($rawValue) || $rawValue === '' || !is_numeric($rawValue)) {
                    return null;
                }

                return $rawValue;
            }

            // Para cualquier otro campo (VARCHAR, CHAR, etc.)
            if (is_null($rawValue) || (is_string($rawValue) && trim($rawValue) === '')) {
                return null;
            }

            return $rawValue;
        };
        $sanitizeFinalValue = function ($field, $value) use ($datetimeFields, $bitFields, $numericFields) {

            $charLimits = [
                'PERS_ACTUALIZAR' => 300,
                'CONTROL_VERIF_TRANS' => 50,
                'PERS_OBSERVACIONES' => 300,
                'OBS_CESE' => 500,
                'MOTI_CESE' => 200,
                'OBSERVACIONES' => 200,
            ];

            if (isset($charLimits[$field]) && is_string($value) && strlen($value) > $charLimits[$field]) {
                $value = substr($value, 0, $charLimits[$field]);
            }

            if (is_string($value) && strlen($value) > 50 && $field === 'CONTROL_VERIF_TRANS') {
                $value = substr($value, 0, 50);
            }
            // Normalizar strings
            if (is_string($value)) {
                $value = trim($value);

                // Strings "vacíos" o basura común => null
                if ($value === '' || $value === '?' || strtolower($value) === 'null' || strtolower($value) === 'undefined') {
                    $value = null;
                }
            }

            // DATETIME
            if (in_array($field, $datetimeFields, true)) {
                if ($value === null) {
                    return null;
                }

                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d\TH:i:s.v');
                }

                if (is_string($value)) {
                    $value = trim($value);

                    // YYYY-MM-DD
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        return $value . 'T00:00:00.000';
                    }

                    // YYYY-MM-DD HH:MM:SS
                    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $value)) {
                        return str_replace(' ', 'T', $value) . '.000';
                    }

                    // YYYY-MM-DD HH:MM:SS.mmm
                    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}\.\d{3}$/', $value)) {
                        return str_replace(' ', 'T', $value);
                    }

                    // YYYY-MM-DDTHH:MM:SS
                    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
                        return $value . '.000';
                    }

                    // YYYY-MM-DDTHH:MM:SS.mmm
                    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}$/', $value)) {
                        return $value;
                    }

                    // DD/MM/YYYY
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                        [$d, $m, $y] = explode('/', $value);

                        return "{$y}-{$m}-{$d}T00:00:00.000";
                    }

                    // DD/MM/YYYY HH:MM:SS
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}\s\d{2}:\d{2}:\d{2}$/', $value)) {
                        [$date, $time] = explode(' ', $value);
                        [$d, $m, $y] = explode('/', $date);

                        return "{$y}-{$m}-{$d}T{$time}.000";
                    }
                }

                return null;
            }

            // BIT
            if (in_array($field, $bitFields, true)) {
                if ($value === null) {
                    return 0;
                }

                if (is_string($value)) {
                    $v = strtoupper(trim($value));
                    if (in_array($v, ['1', 'SI', 'SÍ', 'TRUE', 'YES'], true)) {
                        return 1;
                    }
                    if (in_array($v, ['0', 'NO', 'FALSE'], true)) {
                        return 0;
                    }
                }

                return (int) ((bool) $value);
            }

            // NUMERIC
            if (in_array($field, $numericFields, true)) {
                if ($value === null) {
                    return null;
                }

                if (is_string($value)) {
                    $value = trim($value);

                    if ($value === '') {
                        return null;
                    }

                    // Permitir coma decimal
                    $value = str_replace(',', '.', $value);
                }

                return is_numeric($value) ? $value : null;
            }

            // Cualquier otro campo
            if (is_string($value)) {
                $value = trim($value);

                return $value === '' ? null : $value;
            }

            return $value;
        };

        // ✅ 5. Construir array de datos COMPLETO (código igual que antes)
        $updates = [
            'CODI_PERS' => $codiPers,

            // Campos que vienen del formulario (con fallback a tablas)
            'NRO_DOCU_IDEN' => $getValue('dni', 'NRO_DOCU_IDEN'),
            'PERS_EMAIL' => $getValue('correo', 'PERS_EMAIL'),
            'PERS_TELEFONO' => $getValue('celular', 'PERS_TELEFONO'),
            'PERS_WHATSAPP' => $getValue('whatsapp', 'PERS_WHATSAPP'),
            'DIRECCION' => $getValue('direccion_actual', 'DIRECCION'),
            'PERS_SEXO' => $getValue('sexo', 'PERS_SEXO'),
            'SEXO' => $getValue('sexo', 'SEXO'),
            'ESCI_CODIGO' => $getValue('estado_civil', 'ESCI_CODIGO'),
            'PERS_FECHCADUCADNI' => $getValue('caduca', 'PERS_FECHCADUCADNI'),
            'FECH_NACI' => $getValue('fecha_nacimiento', 'FECH_NACI'),
            'PERS_SNADAR' => $getValue('sabe_nadar', 'PERS_SNADAR'),
            'tipo_sangr' => $getValue('tipo_sangre', 'tipo_sangr'),
            'peso_kilo' => $getValue('peso', 'peso_kilo'),
            'tall_metr' => $getValue('talla', 'tall_metr'),
            'CODI_SIST_PENS' => $getValue('sistema_previsional', 'CODI_SIST_PENS'),
            'ESSALUD' => $getValue('essalud', 'ESSALUD'),
            'PERS_PENSIONISTA' => $getValue('pensionista', 'PERS_PENSIONISTA'),
            'PERS_EMBARGO' => $getValue('embargos', 'PERS_EMBARGO'),
            'PERS_CONSMO' => $getValue('presto_smo', 'PERS_CONSMO'),
            'PERS_DEPT_ACT' => $getValue('departamento_actual', 'PERS_DEPT_ACT'),
            'PERS_PROV_ACT' => $getValue('provincia_actual', 'PERS_PROV_ACT'),
            'PERS_DIST_ACT' => $getValue('distrito_actual', 'PERS_DIST_ACT'),
            'PERS_DPTO_DIRDNI' => $getValue('departamento_dni', 'PERS_DPTO_DIRDNI'),
            'PERS_PROV_DIRDNI' => $getValue('provincia_dni', 'PERS_PROV_DIRDNI'),
            'PERS_DIST_DIRDNI' => $getValue('distrito_dni', 'PERS_DIST_DIRDNI'),
            'PERS_DIREC_DNI' => $getValue('direccion_dni', 'PERS_DIREC_DNI'),
            'PERS_ZONA_DIRDNI' => $getValue('zona_dirdni', 'PERS_ZONA_DIRDNI'),
            'TIZO_CODIGO' => $getValue('tipo_zona_dni', 'TIZO_CODIGO'),
            'PERS_GRADO_INSTRUCCION' => $getValue('grado_instruccion', 'PERS_GRADO_INSTRUCCION'),
            'CARR_CODIGO' => empty($getValue('carrera', 'CARR_CODIGO')) ? null : $getValue('carrera', 'CARR_CODIGO'),

            // EN insertOrUpdateDJ2026Personal también (más abajo)

            'IEDU_CODIGO' =>
                ($getValue('carrera', 'CARR_CODIGO') == '999999') ?
                '199999999'
                :
                (empty($getValue('institucion', 'IEDU_CODIGO')) ? null : $getValue('institucion', 'IEDU_CODIGO')),


            'EGRESO_EDUCATIVO' => $getValue('anio_egreso', 'EGRESO_EDUCATIVO'),
            'PERS_CONDISCAMEC' => $getValue('curso_sucamec', 'PERS_CONDISCAMEC'),
            'PERS_NRODISCAMEC' => $getValue('sucamec_obs', 'PERS_NRODISCAMEC'),
            'PERS_LUGARSMO' => $getValue('lugar_smo', 'PERS_LUGARSMO'),
            //'PERS_CONLICARMAS' => $getValue('licencia_arma', 'PERS_CONLICARMAS'),
            //'PERS_CONLICARMAS' => 'SI', // o extraer el valor del JSON
            'PERS_NROLICENCIA' => $getValue('licencia_arma', 'PERS_NROLICENCIA'),
            'PERS_TIPOARMA' => $getValue('tipo_arma', 'PERS_TIPOARMA'),
            'PERS_CONARMAS' => $getValue('arma_propia', 'PERS_CONARMAS'),
            'PERS_BREVETE' => $getValue('brevete', 'PERS_BREVETE'),
            'CLASE_BREVETE' => $getValue('clase_brevete', 'CLASE_BREVETE'),
            'PERS_TIPO_VEHICULO' => $getValue('tipo_vehiculo', 'PERS_TIPO_VEHICULO'),
            'PERS_VEHICULO_PROPIO' => $getValue('vehiculo_propio', 'PERS_VEHICULO_PROPIO'),
            'PERS_NOMCONTACTO' => $getValue('contacto_emergencia', 'PERS_NOMCONTACTO'),
            'PERS_NROEMERGENCIA' => $getValue('celular_emergencia', 'PERS_NROEMERGENCIA'),
            //'PERS_CONYUGE' => $getValue('parentesco_emergencia', 'PERS_CONYUGE'),
            'PERS_EMERC_FAMILIAR' => $getValue('parentesco_emergencia', 'PERS_EMERC_FAMILIAR'),
            'PERS_CTRABANT' => $getValue('empresa_anterior', 'PERS_CTRABANT'),
            'PERS_CARGOTRABANT' => $getValue('cargo_anterior', 'PERS_CARGOTRABANT'),
            'PERS_DURACIONANT' => $getValue('duracion_anterior', 'PERS_DURACIONANT'),
            'dj2026_banco' => $getValue('cuenta_banco', 'dj2026_banco'),
            'NACIONALIDAD' => $getValue('nacionalidad', null, 'NACIONALIDAD') ?? $getValue('ciudad_nacimiento', null, 'NACIONALIDAD'),
            'dj2026_ocupacion_principal' => $getValue('ocupacion_principal', 'dj2026_ocupacion_principal'),
            'dj2026_experiencia_anios' => $getValue('experiencia_anios', 'dj2026_experiencia_anios'),
            'dj2026_experiencia_meses' => $getValue('experiencia_meses', 'dj2026_experiencia_meses'),
            'dj2026_familiar_empresa' => $getValue('familiar_empresa', 'dj2026_familiar_empresa'),
            'dj2026_familiar_nombre' => $getValue('familiar_nombre', 'dj2026_familiar_nombre'),
            'dj2026_familiar_parentesco' => $getValue('familiar_parentesco', 'dj2026_familiar_parentesco'),
            // 'dj2026_cantprofesion' => isset($data['ocupacion_alterna']) ? count(array_filter($data['ocupacion_alterna'])) : 0,
            'dj2026_laboral_1' => $getValue('dj2026_laboral_1', 'dj2026_laboral_1')
                ? strtoupper(trim($getValue('dj2026_laboral_1', 'dj2026_laboral_1')))
                : null,
            'dj2026_laboral_2' => $getValue('dj2026_laboral_2', 'dj2026_laboral_2')
                ? strtoupper(trim($getValue('dj2026_laboral_2', 'dj2026_laboral_2')))
                : null,
            'dj2026_cantprofesion' => ((!empty($data['dj2026_laboral_1']) ? 1 : 0) + (!empty($data['dj2026_laboral_2']) ? 1 : 0)),

            // ✅ TODOS LOS DEMÁS CAMPOS (con cascada: formulario → migra → original → null)
            'CODI_TIPO_DOCU' => $getValue('CODI_TIPO_DOCU', 'CODI_TIPO_DOCU'),
            // Nombres: el formulario envía nombre1/nombre2/apellido_paterno/apellido_materno
            // (antes se buscaban las claves NOMB_*/APEL_* y nunca se guardaban los cambios)
            'APEL_1' => strtoupper(trim((string) $getValue('apellido_paterno', 'APEL_1', 'APEL_1'))) ?: null,
            'APEL_2' => strtoupper(trim((string) $getValue('apellido_materno', 'APEL_2', 'APEL_2'))) ?: null,
            'NOMB_1' => strtoupper(trim((string) $getValue('nombre1', 'NOMB_1', 'NOMB_1'))) ?: null,
            'NOMB_2' => strtoupper(trim((string) $getValue('nombre2', 'NOMB_2', 'NOMB_2'))) ?: null,
            'SIST_PENS_TIPOCOMI' => $getValue('SIST_PENS_TIPOCOMI', 'SIST_PENS_TIPOCOMI'),
            'CODI_CARG' => $getValue('cargo', 'CODI_CARG'),
            'CODI_AREA' => $getValue('CODI_AREA', 'CODI_AREA'),
            'CODI_MONE_BASI' => '01',
            'SUEL_BASI' => $getValue('SUEL_BASI', 'SUEL_BASI'),
            // FECH_INGRE solo cambia al registrar una DJ nueva o recontratar.
            // FECH_INGRE_PLANILLA queda fuera de la DJ.
            'FECH_INGRE' => in_array($source, ['nueva_dj', 'recontratacion'], true)
                ? $getValue('fecha_ingreso_solmar', 'FECH_INGRE', 'FECH_INGRE')
                : $getValue('FECH_INGRE', 'FECH_INGRE'),
            'CARN_ESSALUD' => $getValue('CARN_ESSALUD', 'CARN_ESSALUD'),
            'ESTA_CIVI' => $getValue('ESTA_CIVI', 'ESTA_CIVI'),
            'ASIG_FAMI' => $getValue('ASIG_FAMI', 'ASIG_FAMI'),
            'ESTA_ACTI' => $getValue('ESTA_ACTI', 'ESTA_ACTI'),
            'SCRT' => $getValue('SCRT', 'SCRT'),
            'COMI_PESC' => $getValue('COMI_PESC', 'COMI_PESC'),
            'NRO_CUPSS' => $getValue('NRO_CUPSS', 'NRO_CUPSS'),
            'AFIL_SIND' => $getValue('AFIL_SIND', 'AFIL_SIND'),
            'NRO_FICHA' => $getValue('NRO_FICHA', 'NRO_FICHA'),
            'TIPO_CONT' => (['01' => 'O', '02' => 'A', '03' => 'O', '05' => 'A', '06' => 'O'][$data['tipo_personal'] ?? ''] ?? $getValue('TIPO_CONT', 'TIPO_CONT')),
            'CODI_MONE' => $getValue('CODI_MONE', 'CODI_MONE'),
            'SUEL_NETO' => $getValue('SUEL_NETO', 'SUEL_NETO'),
            'CARG_FAMI' => $getValue('CARG_FAMI', 'CARG_FAMI'),
            'TIPO_SITU_LABO' => $getValue('TIPO_SITU_LABO', 'TIPO_SITU_LABO'),
            'SEGU_VIDA_LEY' => $getValue('SEGU_VIDA_LEY', 'SEGU_VIDA_LEY'),
            'PERS_CONBREVETE' => $getValue('PERS_CONBREVETE', 'PERS_CONBREVETE'),
            'FECH_CESE' => $getValue('FECH_CESE', 'FECH_CESE'),
            'CLAVE' => $getValue('CLAVE', 'CLAVE'),
            'JUBILADO' => $getValue('JUBILADO', 'JUBILADO'),
            'FORM_PAGO' => $getValue('FORM_PAGO', 'FORM_PAGO'),
            'CODI_ANTI' => $getValue('CODI_ANTI', 'CODI_ANTI'),
            'MONT_PAGO_COME' => $getValue('MONT_PAGO_COME', 'MONT_PAGO_COME'),
            'HORA_LABO' => $getValue('HORA_LABO', 'HORA_LABO'),
            'CODI_UNID_OPER' => $getValue('CODI_UNID_OPER', 'CODI_UNID_OPER'),
            'PERS_FECHEMISIONDNI' => $getValue('PERS_FECHEMISIONDNI', 'PERS_FECHEMISIONDNI'),
            'CODI_RELA' => $getValue('CODI_RELA', 'CODI_RELA'),
            'MOTI_CESE' => $getValue('MOTI_CESE', 'MOTI_CESE'),
            'PROVINCIA' => $getValue('provincia_actual', 'PROVINCIA'),
            'DISTRITO' => $getValue('distrito_actual', 'DISTRITO'),
            'VCMTO' => $getValue('VCMTO', 'VCMTO'),
            'DEPARTAMENTO' => $getValue('departamento_actual', 'DEPARTAMENTO'),
            'OBSERVACIONES' => $getValue('OBSERVACIONES', 'OBSERVACIONES'),
            'CODI_TIPO_RIES' => $getValue('CODI_TIPO_RIES', 'CODI_TIPO_RIES'),
            'UBIGEO' => $getValue('UBIGEO', 'UBIGEO'),
            'FOTO_SI_NO' => $getValue('FOTO_SI_NO', 'FOTO_SI_NO'),
            'FECH_INIC_AFIL' => $getValue('FECH_INIC_AFIL', 'FECH_INIC_AFIL'),
            'DIST_NACI' => $getValue('distrito_nac', 'DIST_NACI'),
            'PROV_NACI' => $getValue('PROV_NACI', 'PROV_NACI'),
            'UTIL03' => $getValue('UTIL03', 'UTIL03'),
            'CODI_AREA_GRUP' => $getValue('CODI_AREA_GRUP', 'CODI_AREA_GRUP'),
            'CODI_SUB_AREA_GRUP' => $getValue('CODI_SUB_AREA_GRUP', 'CODI_SUB_AREA_GRUP'),
            'HORA_AUTO' => $getValue('HORA_AUTO', 'HORA_AUTO'),
            'GRAT' => $getValue('GRAT', 'GRAT'),
            'VACA' => $getValue('VACA', 'VACA'),
            'codi_luga_trab' => $getValue('codi_luga_trab', 'codi_luga_trab'),
            'AFECTO_LEY' => $getValue('AFECTO_LEY', 'AFECTO_LEY'),
            'fotocheck' => $getValue('fotocheck', 'fotocheck'),
            'horario' => $getValue('horario', 'horario'),
            'CODI_CATE_TRAB' => $getValue('CODI_CATE_TRAB', 'CODI_CATE_TRAB'),
            'DEPA_CODIGO_NACI' => $getValue('departamento_nac', 'DEPA_CODIGO_NACI'),
            'PROVI_CODIGO_NACI' => $getValue('provincia_nac', 'PROVI_CODIGO_NACI'),
            'DEPA_CODIGO_DOMI' => $getValue('DEPA_CODIGO_DOMI', 'DEPA_CODIGO_DOMI'),
            'PROVI_CODIGO_DOMI' => $getValue('PROVI_CODIGO_DOMI', 'PROVI_CODIGO_DOMI'),
            'apor_essa' => $getValue('apor_essa', 'apor_essa'),
            'apor_sena' => $getValue('apor_sena', 'apor_sena'),
            'apor_senc' => $getValue('apor_senc', 'apor_senc'),
            'apor_cona' => $getValue('apor_cona', 'apor_cona'),
            'PERS_LUEXPDNI' => $getValue('PERS_LUEXPDNI', 'PERS_LUEXPDNI'),
            'PERS_NRORUC' => $getValue('PERS_NRORUC', 'PERS_NRORUC'),
            'PERS_NROLIBM' => $getValue('PERS_NROLIBM', 'PERS_NROLIBM'),
            'PERS_CONHIJOS' => $getValue('PERS_CONHIJOS', 'PERS_CONHIJOS'),
            'PERS_PROFESION' => $getValue('PERS_PROFESION', 'PERS_PROFESION'),
            'PERS_NROANTPOL' => $getValue('PERS_NROANTPOL', 'PERS_NROANTPOL'),
            'PERS_NORANTPEN' => $getValue('PERS_NORANTPEN', 'PERS_NORANTPEN'),
            'PERS_NROLICENCIA' => $getValue('PERS_NROLICENCIA', 'PERS_NROLICENCIA'),
            'PERS_SERIEARMA' => $getValue('PERS_SERIEARMA', 'PERS_SERIEARMA'),
            'PERS_ACEPTADTA' => $getValue('PERS_ACEPTADTA', 'PERS_ACEPTADTA'),
            'PERS_FECHAREG' => $getValue('PERS_FECHAREG', 'PERS_FECHAREG'),
            //'PERS_VIGENCIA' => $getValue('PERS_VIGENCIA', 'PERS_VIGENCIA'),
            'USUA_CODIGO' => $getValue('USUA_CODIGO', 'USUA_CODIGO'),
            'PERS_MARCA' => $getValue('PERS_MARCA', 'PERS_MARCA'),
            'PERS_CALIBRE' => $getValue('PERS_CALIBRE', 'PERS_CALIBRE'),
            'PERS_MODELO' => $getValue('PERS_MODELO', 'PERS_MODELO'),
            'PERS_TIPOTRAB' => $getValue('tipo_personal', 'PERS_TIPOTRAB'),
            'PERS_CONTRATADO' => $getValue('PERS_CONTRATADO', 'PERS_CONTRATADO'),
            'EMPR_CODIGO' => $getValue('EMPR_CODIGO', 'EMPR_CODIGO'),
            'SUCU_CODIGO' => $getValue('SUCU_CODIGO', 'SUCU_CODIGO'),
            'USUA_CODIGO_REG' => $getValue('USUA_CODIGO_REG', 'USUA_CODIGO_REG'),
            'USUA_FECHA_REG' => $getValue('USUA_FECHA_REG', 'USUA_FECHA_REG'),
            'USUA_CODIGO_MOD' => session('usuario') ?? 'SISTEMA',
            'USUA_FECHA_MOD' => now()->format('Y-m-d\TH:i:s.v'),
            'PERS_FECHARETIRO' => $getValue('PERS_FECHARETIRO', 'PERS_FECHARETIRO'),
            'PERS_FLAG' => $getValue('PERS_FLAG', 'PERS_FLAG'),
            'cala_codigo' => $getValue('cala_codigo', 'cala_codigo'),
            'Pers_fech_venc_lic_arm' => $getValue('Pers_fech_venc_lic_arm', 'Pers_fech_venc_lic_arm'),
            'PERS_OBSERVACIONES' => $getValue('PERS_OBSERVACIONES', 'PERS_OBSERVACIONES'),
            'PERS_RESERVA' => $getValue('PERS_RESERVA', 'PERS_RESERVA'),
            'EMPRESA_ASOCIADA_5TA' => $getValue('EMPRESA_ASOCIADA_5TA', 'EMPRESA_ASOCIADA_5TA'),
            'MOCE_CODIGO' => $getValue('MOCE_CODIGO', 'MOCE_CODIGO'),
            'PERS_ACTUALIZAR' => $getValue('PERS_ACTUALIZAR', 'PERS_ACTUALIZAR'),
            'CONTROL_MIGRADO' => $getValue('CONTROL_MIGRADO', 'CONTROL_MIGRADO'),
            'PERS_PARA_FOTOCHECK' => $getValue('PERS_PARA_FOTOCHECK', 'PERS_PARA_FOTOCHECK'),
            'PERS_CON_FOTOCHECK' => $getValue('PERS_CON_FOTOCHECK', 'PERS_CON_FOTOCHECK'),
            'PERS_VERIF_RENIEC' => $getValue('PERS_VERIF_RENIEC', 'PERS_VERIF_RENIEC'),
            'PERS_VERIF_USUARIO' => $getValue('PERS_VERIF_USUARIO', 'PERS_VERIF_USUARIO'),
            'PERS_VERIF_FECHA' => $getValue('PERS_VERIF_FECHA', 'PERS_VERIF_FECHA'),
            'GRADO_INSTRUC_OBS' => $getValue('GRADO_INSTRUC_OBS', 'GRADO_INSTRUC_OBS'),
            'OBS_CESE' => $getValue('OBS_CESE', 'OBS_CESE'),
            'CONTROL_MIGRADO2' => $getValue('CONTROL_MIGRADO2', 'CONTROL_MIGRADO2'),
            'PERS_OMISO_ONPE' => $getValue('PERS_OMISO_ONPE', 'PERS_OMISO_ONPE'),
            'USUA_MOD_PLAN' => $getValue('USUA_MOD_PLAN', 'USUA_MOD_PLAN'),
            'FEC_MOD_PLAN' => $getValue('FEC_MOD_PLAN', 'FEC_MOD_PLAN'),
            'CONTROL_MIGRADO_COPE' => $getValue('CONTROL_MIGRADO_COPE', 'CONTROL_MIGRADO_COPE'),
            'CONTROL_MIGRADO_AUSTRAL' => $getValue('CONTROL_MIGRADO_AUSTRAL', 'CONTROL_MIGRADO_AUSTRAL'),
            'CONTROL_MIGRADO_CONSORCIO' => $getValue('CONTROL_MIGRADO_CONSORCIO', 'CONTROL_MIGRADO_CONSORCIO'),
            'CONTROL_MIGRADO_CODRALUX' => $getValue('CONTROL_MIGRADO_CODRALUX', 'CONTROL_MIGRADO_CODRALUX'),
            'CONTROL_VERIF_TRANS' => $getValue('CONTROL_VERIF_TRANS', 'CONTROL_VERIF_TRANS'),
            'TIZO_CODIGO' => $getValue('TIZO_CODIGO', 'TIZO_CODIGO'),
            'PERS_ZONA_DIRDNI' => $getValue('PERS_ZONA_DIRDNI', 'PERS_ZONA_DIRDNI'),
            'PERS_KM_MZ_DIRDNI' => $getValue('PERS_KM_MZ_DIRDNI', 'PERS_KM_MZ_DIRDNI'),
            'PERS_LOTE_DIRDNI' => $getValue('PERS_LOTE_DIRDNI', 'PERS_LOTE_DIRDNI'),
            'PERS_NRO_DIRDNI' => $getValue('PERS_NRO_DIRDNI', 'PERS_NRO_DIRDNI'),
            'PERS_KM_DIRDNI' => $getValue('PERS_KM_DIRDNI', 'PERS_KM_DIRDNI'),
            'PERS_MZ_DIRDNI' => $getValue('PERS_MZ_DIRDNI', 'PERS_MZ_DIRDNI'),
            'PERS_TRABAJO_ANTERIOR' => $getValue('PERS_TRABAJO_ANTERIOR', 'PERS_TRABAJO_ANTERIOR'),
            'CODI_TIPO_DOCU_ANT' => $getValue('CODI_TIPO_DOCU_ANT', 'CODI_TIPO_DOCU_ANT'),
            'CATEGORIA_BREVETE' => $getValue('tipo_vehiculo', 'CATEGORIA_BREVETE'),
            'FECH_EXP_BREVETE' => $getValue('FECH_EXP_BREVETE', 'FECH_EXP_BREVETE'),
            'FECH_REVAL_BREVETE' => $getValue('FECH_REVAL_BREVETE', 'FECH_REVAL_BREVETE'),
            'RESTRICCION_BREVETE' => $getValue('RESTRICCION_BREVETE', 'RESTRICCION_BREVETE'),
            'MIGRADO' => $getValue('MIGRADO', 'MIGRADO'),
            'SIP_fechaCreacion' => $getValue('SIP_fechaCreacion', 'SIP_fechaCreacion'),
            'SIP_habilitado' => $getValue('SIP_habilitado', 'SIP_habilitado'),
            'SIP_migrado' => 1, // ✅ Siempre 1
            'SIP_activo' => $getValue('SIP_activo', 'SIP_activo'),
            'SIP_fechaModifcacion' => $getValue('SIP_fechaModifcacion', 'SIP_fechaModifcacion'),
            'NO_CADUCA_DNI' => ($data['no_caduca_dni'] ?? '0') === '1' ? 1 : 0,
        ];

        // Si No Caduca está marcado, limpiar PERS_FECHCADUCADNI
        if (($data['no_caduca_dni'] ?? '0') === '1') {
            $updates['PERS_FECHCADUCADNI'] = null;
        }

        foreach ($camposProtegidos as $campoProtegido) {
            unset($updates[$campoProtegido]);
        }

        foreach ($updates as $field => $value) {
            $updates[$field] = $sanitizeFinalValue($field, $value);
        }

        // ✅ 6. Verificar si ya existe en DJ2026_PERSONAL
        $exists = DB::select(
            'SELECT 1 FROM si_solm.dbo.DJ2026_PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        try {
            if (!empty($exists)) {
                // ✅ UPDATE
                $updateFields = [];
                $params = [];

                foreach ($updates as $key => $value) {
                    if ($key === 'CODI_PERS') {
                        continue;
                    }
                    $updateFields[] = "[$key] = ?";
                    $params[] = $value;
                }

                $params[] = $codiPers;

                $sql = 'UPDATE si_solm.dbo.DJ2026_PERSONAL SET ' . implode(', ', $updateFields) . ' WHERE CODI_PERS = ?';

                Log::info('✅ Ejecutando UPDATE en DJ2026_PERSONAL', ['CODI_PERS' => $codiPers]);

                DB::update($sql, $params);

            } else {
                // ✅ INSERT
                $insertFields = array_keys($updates);
                $insertPlaceholders = array_fill(0, count($updates), '?');

                $sqlInsert = 'INSERT INTO si_solm.dbo.DJ2026_PERSONAL (' .
                    implode(', ', array_map(fn($f) => "[$f]", $insertFields)) .
                    ') VALUES (' . implode(', ', $insertPlaceholders) . ')';

                //              dd([
                //     'sql' => $sqlInsert,
                //     'updates' => $updates,
                //     'params' => array_values($updates),
                // ]);

                Log::info('✅ Ejecutando INSERT en DJ2026_PERSONAL', ['CODI_PERS' => $codiPers]);

                DB::insert($sqlInsert, array_values($updates));
            }

            Log::info('✅ DJ2026_PERSONAL guardado exitosamente', ['CODI_PERS' => $codiPers]);

        } catch (\Exception $e) {
            Log::error('❌ Error en INSERT/UPDATE DJ2026_PERSONAL:', [
                'error' => $e->getMessage(),
                'CODI_PERS' => $codiPers,
            ]);
            throw $e;
        }
    }

    /**
     * Sincronizar datos de DJ2026_PERSONAL hacia si_solm.dbo.PERSONAL
     * Solo actualiza columnas donde DJ2026_PERSONAL tenga valor NO NULL.
     * Si DJ2026_PERSONAL tiene NULL en una columna, NO borra lo que ya existe en PERSONAL.
     */
    /**
     * Registra el usuario en el SIP y envía la carta de bienvenida.
     * Flujo (según código PowerBuilder original):
     *   1. Generar contraseña (algoritmo hora: quitar ':' → 1→A, 2→B, 3→C)
     *   2. Hashear con MD5 para almacenar en Seguridad_UsuarioSIP
     *   3. SipCartaElectronica: desactivar cartas previas + insertar nueva
     *   4. Seguridad_UsuarioSIP: INSERT (nuevo) o UPDATE (existente)
     *   5. CMS_Actividad: desactivar eventos pendientes (solo si UPDATE)
     *   6. Enviar correo con contraseña en texto plano
     */
    private function enviarCorreoBienvenidaSip(array $data, string $dni, string $codiPers): void
    {
        try {
            $correo = trim($data['correo'] ?? '');
            if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                Log::warning('BienvenidaSip: correo vacío o inválido, no se registra ni envía.', ['dni' => $dni]);
                return;
            }

            // ── 1. Generar contraseña (algoritmo PowerBuilder) ──
            $password = str_replace([':', '1', '2', '3'], ['', 'A', 'B', 'C'], now()->format('H:i:s'));

            // Hash SIP: doble MD5 de (DNI + password) según función F_SIP_MD5 de SQL Server
            $hash1 = strtoupper(md5($dni . $password));
            $passwordMd5 = strtoupper(md5($hash1));

            $nombreCompleto = trim(
                ($data['nombre1'] ?? '') . ' ' .
                ($data['nombre2'] ?? '') . ' ' .
                ($data['apellido_paterno'] ?? '') . ' ' .
                ($data['apellido_materno'] ?? '')
            );
            if (empty($nombreCompleto)) {
                $nombreCompleto = $data['nombres_apellidos'] ?? 'Usuario SIP';
            }
            $nombreCorto = ucfirst(strtolower(trim($data['nombre1'] ?? explode(' ', $nombreCompleto)[0] ?? 'Usuario')));

            $usuarioReg  = strtoupper(trim((string)(session('usuario') ?? 'SISTEMAS')));
            $empresa     = '01';
            $mes         = (int) now()->format('n');
            $anio        = (int) now()->format('Y');
            $plcaCod     = 'planillaco';
            $fechaCorta  = now()->format('Ymd');   // YYYYMMDD
            $horaCorta   = now()->format('His');   // HHMMSS

            // ── 2. SipCartaElectronica (si_solm): desactivar previas + insertar nueva ──
            DB::connection('sqlsrv')->update(
                "UPDATE si_solm.dbo.SipCartaElectronica SET Vigencia = 0
                 WHERE CodiPers = ? AND Vigencia = 1",
                [$codiPers]
            );
            DB::connection('sqlsrv')->insert(
                "INSERT INTO si_solm.dbo.SipCartaElectronica
                    (CodiPers, Email, FechaEmail, Mes, Anio, PlcaCod, Usuario, Vigencia, Rebote)
                 VALUES (?, ?, GETDATE(), ?, ?, ?, ?, 1, 0)",
                [$codiPers, $correo, $mes, $anio, $plcaCod, $usuarioReg]
            );
            Log::info('BienvenidaSip: SipCartaElectronica registrada.', ['codiPers' => $codiPers]);

            // ── 3. Seguridad_UsuarioSIP (extranet_solmar): verificar existencia ──
            $existe = DB::connection('sqlsrv')->selectOne(
                "SELECT COUNT(*) AS cnt FROM extranet_solmar.dbo.Seguridad_UsuarioSIP WITH (NOLOCK)
                 WHERE usuarioSIP_DNI = ? AND usuarioSIP_CODI_PERS = ?
                   AND usuarioSIP_vigencia = '1' AND usuarioSIP_estado IN ('1','2','0')
                   AND empr_codigo = ?",
                [$dni, $codiPers, $empresa]
            );

            if ((int)($existe->cnt ?? 0) <= 0) {
                // ── 3a. INSERT (usuario nuevo) ──
                DB::connection('sqlsrv')->insert(
                    "INSERT INTO extranet_solmar.dbo.Seguridad_UsuarioSIP
                        (usuarioSIP_CODI_PERS, usuarioSIP_DNI, usuarioSIP_clave, usuarioSIP_vigencia,
                         usuarioSIP_Correo, usuarioSIP_Creacion_fecha, usuarioSIP_Creacion_hora,
                         usuarioSIP_estado, rol_id, auxiliar, empr_codigo,
                         actualizacion_datos, modificado_por, fecha_modificacion)
                     VALUES (?, ?, ?, '1', ?, ?, ?, '2', 4, NULL, ?, NULL, NULL, NULL)",
                    [$codiPers, $dni, $passwordMd5, $correo, $fechaCorta, $horaCorta, $empresa]
                );
                Log::info('BienvenidaSip: usuario INSERTado en Seguridad_UsuarioSIP.', ['dni' => $dni, 'codiPers' => $codiPers]);
            } else {
                // ── 3b. UPDATE (usuario existente) ──
                DB::connection('sqlsrv')->update(
                    "UPDATE extranet_solmar.dbo.Seguridad_UsuarioSIP
                     SET usuarioSIP_estado = '2', usuarioSIP_clave = ?, usuarioSIP_vigencia = '1', usuarioSIP_Correo = ?
                     WHERE usuarioSIP_DNI = ? AND usuarioSIP_CODI_PERS = ?
                       AND usuarioSIP_estado IN ('1','2','0') AND empr_codigo = ?",
                    [$passwordMd5, $correo, $dni, $codiPers, $empresa]
                );
                Log::info('BienvenidaSip: usuario UPDATEado en Seguridad_UsuarioSIP.', ['dni' => $dni, 'codiPers' => $codiPers]);

                // ── 3c. CMS_Actividad: desactivar eventos pendientes ──
                DB::connection('sqlsrv')->update(
                    "UPDATE intranet.dbo.CMS_Actividad
                     SET ESTADO = 0
                     FROM intranet.dbo.CMS_Actividad A
                     INNER JOIN extranet_solmar.dbo.Seguridad_UsuarioSIP U
                             ON U.usuarioSIP_id = A.user_id
                     WHERE U.usuarioSIP_codi_pers = ? AND U.usuarioSIP_vigencia = '1'
                       AND U.usuarioSIP_DNI = ? AND U.empr_codigo = ?
                       AND A.CMS_TIPO_ID = 9 AND A.CodEvento IN ('002','003') AND A.ESTADO = 1",
                    [$codiPers, $dni, $empresa]
                );
                Log::info('BienvenidaSip: CMS_Actividad desactivado.', ['dni' => $dni]);
            }

            // ── 4. Enviar correo con contraseña en texto plano ──
            $datos = [
                'nombre'       => mb_strtoupper($nombreCompleto, 'UTF-8'),
                'nombre_corto' => $nombreCorto,
                'dni'          => $dni,
                'password'     => $password,
                'correo'       => $correo,
            ];

            // Copia a Control de Archivo: si la carta le llega al usuario, también
            // les llega a ellos. (El valor por defecto está en config/mail.php → sip_cc,
            // por si la configuración cacheada no tuviera esa clave.)
            $ccSip = config('mail.sip_cc') ?: ['filecontrol@solsecurity.pe'];

            Mail::mailer('sip')
                ->to($correo)
                ->cc($ccSip)
                ->send(new \App\Mail\BienvenidaSipMail($datos));

            Log::info('BienvenidaSip: correo enviado exitosamente.', ['dni' => $dni, 'correo' => $correo, 'cc' => $ccSip]);
        } catch (\Exception $e) {
            Log::error('BienvenidaSip: error en el proceso: ' . $e->getMessage(), ['dni' => $dni, 'codiPers' => $codiPers]);
        }
    }

    private function syncDJ2026ToPersonal($codiPers, array $columnasExcluidas = [])
    {
        // 1. Obtener el registro recién guardado en DJ2026_PERSONAL
        $djData = DB::select(
            'SELECT * FROM si_solm.dbo.DJ2026_PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        if (empty($djData)) {
            Log::warning('syncDJ2026ToPersonal: No se encontró registro en DJ2026_PERSONAL', ['CODI_PERS' => $codiPers]);

            return;
        }

        // 2. Obtener registro actual de PERSONAL
        $personalData = DB::select(
            'SELECT * FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        if (empty($personalData)) {
            Log::info('syncDJ2026ToPersonal: No existe registro en PERSONAL, no se sincroniza', ['CODI_PERS' => $codiPers]);

            return;
        }

        $djRecord = (array) $djData[0];
        $personalRecord = (array) $personalData[0];

        // 3. Campos datetime que necesitan sanitización de formato
        $datetimeFields = [
            'FECH_INGRE',
            'FECH_NACI',
            'FECH_CESE',
            'PERS_FECHEMISIONDNI',
            'PERS_FECHCADUCADNI',
            'VCMTO',
            'FECH_INIC_AFIL',
            'PERS_FECHAREG',
            'USUA_FECHA_REG',
            'USUA_FECHA_MOD',
            'PERS_FECHARETIRO',
            'Pers_fech_venc_lic_arm',
            'PERS_VERIF_FECHA',
            'FEC_MOD_PLAN',
            'FECH_EXP_BREVETE',
            'FECH_REVAL_BREVETE',
        ];

        // 4. Columnas que se deben sincronizar (comunes entre DJ2026_PERSONAL y PERSONAL)
        // Excluimos CODI_PERS (es la PK) y campos exclusivos de DJ2026 que no existen en PERSONAL
        $columnsToSync = [
            'NRO_DOCU_IDEN',
            'CODI_TIPO_DOCU',
            'PERS_VEHICULO_PROPIO',
            'APEL_1',
            'APEL_2',
            'NOMB_1',
            'NOMB_2',
            'CODI_SIST_PENS',
            'SIST_PENS_TIPOCOMI',
            'CODI_CARG',
            'CODI_AREA',
            'CODI_MONE_BASI',
            'SUEL_BASI',
            'FECH_INGRE',
            'ESSALUD',
            'CARN_ESSALUD',
            'ESTA_CIVI',
            'FECH_NACI',
            'ASIG_FAMI',
            'ESTA_ACTI',
            'SCRT',
            'COMI_PESC',
            'NRO_CUPSS',
            'AFIL_SIND',
            'NRO_FICHA',
            'TIPO_CONT',
            'CODI_MONE',
            'SUEL_NETO',
            'CARG_FAMI',
            'SEXO',
            'NACIONALIDAD',
            'DIRECCION',
            'PERS_EMAIL',
            'TIPO_SITU_LABO',
            'SEGU_VIDA_LEY',
            'PERS_CONBREVETE',
            'PERS_BREVETE',
            'FECH_CESE',
            'CLAVE',
            'JUBILADO',
            'FORM_PAGO',
            'CODI_ANTI',
            'MONT_PAGO_COME',
            'HORA_LABO',
            'CODI_UNID_OPER',
            'PERS_FECHEMISIONDNI',
            'PERS_FECHCADUCADNI',
            'CODI_RELA',
            'MOTI_CESE',
            'PROVINCIA',
            'DISTRITO',
            'VCMTO',
            'DEPARTAMENTO',
            'OBSERVACIONES',
            'CODI_TIPO_RIES',
            'UBIGEO',
            'FOTO_SI_NO',
            'FECH_INIC_AFIL',
            'DIST_NACI',
            'PROV_NACI',
            'UTIL03',
            'CODI_AREA_GRUP',
            'CODI_SUB_AREA_GRUP',
            'HORA_AUTO',
            'GRAT',
            'VACA',
            'codi_luga_trab',
            'AFECTO_LEY',
            'fotocheck',
            'horario',
            'CODI_CATE_TRAB',
            'ESCI_CODIGO',
            'DEPA_CODIGO_NACI',
            'PROVI_CODIGO_NACI',
            'DEPA_CODIGO_DOMI',
            'PROVI_CODIGO_DOMI',
            'apor_essa',
            'apor_sena',
            'apor_senc',
            'apor_cona',
            'tipo_sangr',
            'peso_kilo',
            'tall_metr',
            'PERS_LUEXPDNI',
            'PERS_NRORUC',
            'PERS_NROLIBM',
            //'PERS_CONYUGE',
            'PERS_EMERC_FAMILIAR',
            'PERS_CONHIJOS',
            'PERS_PROFESION',
            'PERS_NROANTPOL',
            'PERS_NORANTPEN',
            'PERS_CTRABANT',
            'PERS_CARGOTRABANT',
            'PERS_DURACIONANT',
            'PERS_SNADAR',
            'PERS_CONSMO',
            'PERS_LUGARSMO',
            'PERS_CONDISCAMEC',
            'PERS_NRODISCAMEC',
            'PERS_CONLICARMAS',
            'PERS_NROLICENCIA',
            'PERS_CONARMAS',
            'PERS_SERIEARMA',
            'PERS_TIPOARMA',
            'PERS_ACEPTADTA',
            'PERS_NROEMERGENCIA',
            'PERS_NOMCONTACTO',
            'PERS_FECHAREG',
            'PERS_VIGENCIA',
            'USUA_CODIGO',
            'PERS_TELEFONO',
            'PERS_MARCA',
            'PERS_CALIBRE',
            'PERS_MODELO',
            'PERS_TIPOTRAB',
            'EMPR_CODIGO',
            'SUCU_CODIGO',
            'USUA_CODIGO_REG',
            'USUA_FECHA_REG',
            'USUA_CODIGO_MOD',
            'USUA_FECHA_MOD',
            'PERS_FECHARETIRO',
            'PERS_FLAG',
            'cala_codigo',
            'Pers_fech_venc_lic_arm',
            'PERS_OBSERVACIONES',
            'PERS_RESERVA',
            'EMPRESA_ASOCIADA_5TA',
            'MOCE_CODIGO',
            'PERS_ACTUALIZAR',
            'PERS_SEXO',
            'PERS_GRADO_INSTRUCCION',
            'PERS_DIREC_DNI',
            'CONTROL_MIGRADO',
            'PERS_PARA_FOTOCHECK',
            'PERS_CON_FOTOCHECK',
            'PERS_VERIF_RENIEC',
            'PERS_VERIF_USUARIO',
            'PERS_VERIF_FECHA',
            'PERS_DPTO_DIRDNI',
            'PERS_PROV_DIRDNI',
            'PERS_DIST_DIRDNI',
            'GRADO_INSTRUC_OBS',
            'OBS_CESE',
            'CONTROL_MIGRADO2',
            'PERS_PENSIONISTA',
            'PERS_OMISO_ONPE',
            'USUA_MOD_PLAN',
            'FEC_MOD_PLAN',
            'CONTROL_MIGRADO_COPE',
            'CONTROL_MIGRADO_AUSTRAL',
            'CONTROL_MIGRADO_CONSORCIO',
            'CONTROL_MIGRADO_CODRALUX',
            'CONTROL_VERIF_TRANS',
            'TIZO_CODIGO',
            'PERS_ZONA_DIRDNI',
            'PERS_KM_MZ_DIRDNI',
            'PERS_LOTE_DIRDNI',
            'PERS_NRO_DIRDNI',
            'PERS_KM_DIRDNI',
            'PERS_MZ_DIRDNI',
            'IEDU_CODIGO',
            'CARR_CODIGO',
            'EGRESO_EDUCATIVO',
            'PERS_TRABAJO_ANTERIOR',
            'CODI_TIPO_DOCU_ANT',
            'CLASE_BREVETE',
            'CATEGORIA_BREVETE',
            'FECH_EXP_BREVETE',
            'FECH_REVAL_BREVETE',
            'RESTRICCION_BREVETE',
            'PERS_DEPT_ACT',
            'PERS_PROV_ACT',
            'PERS_DIST_ACT',
            'PERS_EMBARGO',
            'PERS_WHATSAPP',
            'PERS_SMO',
            'dj2026_familiar_empresa',
            'dj2026_banco',
            'dj2026_cantprofesion',
            'dj2026_ocupacion_principal',
            'dj2026_experiencia_anios',
            'dj2026_experiencia_meses',
            'dj2026_familiar_nombre',
            'dj2026_familiar_parentesco',
            'dj2026_laboral_1',
            'dj2026_laboral_2',
            'NO_CADUCA_DNI',
        ];

        // 5. Construir UPDATE dinámico: solo columnas donde DJ2026 NO es NULL
        $updateFields = [];
        $params = [];

        foreach ($columnsToSync as $col) {
            if (in_array($col, $columnasExcluidas, true)) {
                continue;
            }

            // Solo actualizar si DJ2026_PERSONAL tiene valor (no importa si PERSONAL ya tiene dato)
            if (array_key_exists($col, $djRecord) && !is_null($djRecord[$col])) {
                $value = $djRecord[$col];

                // Sanitizar campos datetime para SQL Server
                if (in_array($col, $datetimeFields, true)) {
                    $value = $this->sanitizeDatetimeForPersonal($value);
                    if (is_null($value)) {
                        continue; // Fecha inválida, no actualizar esta columna
                    }
                }

                $updateFields[] = "[$col] = ?";
                $params[] = $value;
            }
        }

        if (empty($updateFields)) {
            Log::info('syncDJ2026ToPersonal: No hay columnas con valor para sincronizar', ['CODI_PERS' => $codiPers]);

            return;
        }

        // 6. Ejecutar UPDATE en PERSONAL
        $params[] = $codiPers;
        $sql = 'UPDATE si_solm.dbo.PERSONAL SET ' . implode(', ', $updateFields) . ' WHERE CODI_PERS = ?';

        try {
            DB::update($sql, $params);
            Log::info('syncDJ2026ToPersonal: PERSONAL actualizado correctamente', [
                'CODI_PERS' => $codiPers,
                'columnas_actualizadas' => count($updateFields),
            ]);
        } catch (\Exception $e) {
            Log::error('syncDJ2026ToPersonal: Error al actualizar PERSONAL', [
                'CODI_PERS' => $codiPers,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sanitizar valor datetime para que SQL Server lo acepte.
     * Usa formato YYYYMMDD HH:MM:SS.mmm (sin guiones en fecha) que es
     * SIEMPRE seguro en SQL Server independientemente del DATEFORMAT/LANGUAGE.
     */
    private function sanitizeDatetimeForPersonal($value)
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        // Si es un objeto DateTime, formatear directamente al formato seguro
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Ymd H:i:s.v');
        }

        if (is_string($value)) {
            $value = trim($value);

            // Descartar valores inválidos
            if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
                return null;
            }

            // Reemplazar T por espacio
            $value = str_replace('T', ' ', $value);

            // Intentar parsear con DateTime para cualquier formato de entrada
            try {
                $dt = new \DateTime($value);

                // Formato YYYYMMDD HH:MM:SS.mmm - seguro para SQL Server con cualquier DATEFORMAT
                return $dt->format('Ymd H:i:s.v');
            } catch (\Exception $e) {
                Log::warning('sanitizeDatetimeForPersonal: Fecha no parseable', ['value' => $value]);

                return null;
            }
        }

        return null;
    }

    // ============================================
    // MÉTODOS PRIVADOS AUXILIARES
    // ============================================

    private function updateMigraPersonal($codiPers, $data)
    {
        // Obtener datos actuales
        $current = DB::select(
            'SELECT * FROM sisolm_web.dbo.sw_MIGRA_PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        $base = !empty($current) ? (array) $current[0] : ['CODI_PERS' => $codiPers];

        // ✅ MAPEAR DESDE LOS NOMBRES DEL FORMULARIO HTML
        $updates = array_merge($base, [
            'NRO_DOCU_IDEN' => $data['dni'] ?? $base['NRO_DOCU_IDEN'] ?? '',
            'NOMB_1' => $this->extraerNombre($data['nombres_apellidos'] ?? '', 0),
            'NOMB_2' => $this->extraerNombre($data['nombres_apellidos'] ?? '', 1),
            'APEL_1' => $this->extraerApellido($data['nombres_apellidos'] ?? '', 0),
            'APEL_2' => $this->extraerApellido($data['nombres_apellidos'] ?? '', 1),
            'PERS_EMAIL' => $data['correo'] ?? '',
            'PERS_TELEFONO' => $data['celular'] ?? '',
            'PERS_WHATSAPP' => $data['whatsapp'] ?? '',
            'DIRECCION' => $data['direccion_actual'] ?? '',
            'PERS_SEXO' => $data['sexo'] ?? 'M',
            'SEXO' => $data['sexo'] ?? 'M',
            'ESCI_CODIGO' => $data['estado_civil'] ?? '',
            'PERS_FECHCADUCADNI' => $data['caduca'] ?? null,
            'FECH_NACI' => $data['fecha_nacimiento'] ?? null,
            'PERS_SNADAR' => $data['sabe_nadar'] ?? 'NO',
            'tipo_sangr' => $data['tipo_sangre'] ?? null,
            'peso_kilo' => $data['peso'] ?? null,
            'tall_metr' => $data['talla'] ?? null,
            'CODI_SIST_PENS' => $data['sistema_previsional'] ?? '',
            'ESSALUD' => $data['essalud'] ?? 'NO',
            'PERS_PENSIONISTA' => $data['pensionista'] ?? 'NO',
            'PERS_EMBARGO' => $data['embargos'] ?? 'NO',
            'PERS_CONSMO' => strtoupper(trim($data['presto_smo'] ?? 'NO')),
            'PERS_DEPT_ACT' => $data['departamento_actual'] ?? null,
            'PERS_PROV_ACT' => $data['provincia_actual'] ?? null,
            'PERS_DIST_ACT' => $data['distrito_actual'] ?? null,
            'PERS_DPTO_DIRDNI' => $data['departamento_dni'] ?? null,
            'PERS_PROV_DIRDNI' => $data['provincia_dni'] ?? null,
            'PERS_DIST_DIRDNI' => $data['distrito_dni'] ?? null,
            'PERS_DIREC_DNI' => $data['direccion_dni'] ?? null,
            'PERS_GRADO_INSTRUCCION' => $data['grado_instruccion'] ?? null,
            'CARR_CODIGO' => !empty($data['carrera']) ? $data['carrera'] : null,
            'IEDU_CODIGO' => $data['institucion'] ?? null,
            'EGRESO_EDUCATIVO' => $data['anio_egreso'] ?? null,
            'PERS_CONDISCAMEC' => $data['curso_sucamec'] ?? 'NO',
            'PERS_NRODISCAMEC' => $data['sucamec_obs'] ?? null,
            //'PERS_SMO' => $data['smo'] ?? 'NO',
            'PERS_LUGARSMO' => strtoupper(trim($data['lugar_smo'] ?? '')) ?: null,
            'PERS_NROLICENCIA' => $data['licencia_arma'] ?? null,
            'PERS_TIPOARMA' => $data['tipo_arma'] ?? null,
            'PERS_CONARMAS' => $data['arma_propia'] ?? 'NO',
            'PERS_BREVETE' => $data['brevete'] ?? null,
            'CLASE_BREVETE' => $data['clase_brevete'] ?? null,
            'PERS_TIPO_VEHICULO' => $data['tipo_vehiculo'] ?? null,
            'PERS_VEHICULO_PROPIO' => $data['vehiculo_propio'] ?? 'NO',
            'PERS_NOMCONTACTO' => $data['contacto_emergencia'] ?? '',
            'PERS_NROEMERGENCIA' => $data['celular_emergencia'] ?? '',
            //'PERS_CONYUGE' => $data['parentesco_emergencia'] ?? null,
            'PERS_EMERC_FAMILIAR' => $data['parentesco_emergencia'] ?? null,
            'PERS_CTRABANT' => $data['empresa_anterior'] ?? null,
            'PERS_CARGOTRABANT' => $data['cargo_anterior'] ?? null,
            'PERS_DURACIONANT' => $data['duracion_anterior'] ?? null,
            'dj2026_banco' => $data['cuenta_banco'] ?? null,
            'NACIONALIDAD' => $data['nacionalidad'] ?? null,
            'dj2026_ocupacion_principal' => $data['ocupacion_principal'] ?? null,
            'dj2026_experiencia_anios' => $data['experiencia_anios'] ?? null,
            'dj2026_familiar_empresa' => $data['familiar_empresa'] ?? 'NO',
            'dj2026_familiar_nombre' => $data['familiar_nombre'] ?? null,
            'dj2026_familiar_parentesco' => $data['familiar_parentesco'] ?? null,
            'dj2026_cantprofesion' => isset($data['ocupacion_alterna']) ? count(array_filter($data['ocupacion_alterna'])) : 0,
        ]);

        // Construir UPDATE dinámico
        $updateFields = [];
        $params = [];

        foreach ($updates as $key => $value) {
            if ($key === 'CODI_PERS') {
                continue;
            }
            $updateFields[] = "[$key] = ?";
            $params[] = $value;
        }

        $params[] = $codiPers;

        $sql = 'UPDATE sisolm_web.dbo.sw_MIGRA_PERSONAL SET ' . implode(', ', $updateFields) . ' WHERE CODI_PERS = ?';

        $affected = DB::update($sql, $params);

        // Si no afectó ninguna fila, hacer INSERT
        if ($affected === 0) {
            $insertFields = array_keys($updates);
            $insertPlaceholders = array_fill(0, count($updates), '?');

            $sqlInsert = 'INSERT INTO sisolm_web.dbo.sw_MIGRA_PERSONAL (' .
                implode(', ', array_map(fn($f) => "[$f]", $insertFields)) .
                ') VALUES (' . implode(', ', $insertPlaceholders) . ')';

            DB::insert($sqlInsert, array_values($updates));
        }
    }

    // ✅ AGREGAR ESTAS FUNCIONES HELPER
    private function extraerNombre($nombreCompleto, $index)
    {
        $partes = preg_split('/\s+/', trim($nombreCompleto));
        // Asumiendo formato: NOMBRE1 NOMBRE2 APELLIDO1 APELLIDO2
        if (count($partes) >= 3) {
            return $partes[$index] ?? '';
        }

        return '';
    }

    private function extraerApellido($nombreCompleto, $index)
    {
        $partes = preg_split('/\s+/', trim($nombreCompleto));
        if (count($partes) >= 3) {
            $apellidoIndex = $index + 2; // Los apellidos empiezan en posición 2

            return $partes[$apellidoIndex] ?? '';
        }

        return '';
    }

    private function migrarPersonal($codiPers)
    {
        // Verificar si ya existe en DJ2026_PERSONAL
        $exists = DB::select(
            'SELECT 1 FROM si_solm.dbo.DJ2026_PERSONAL WHERE CODI_PERS = ?',
            [$codiPers]
        );

        if (!empty($exists)) {
            // Ya existe, hacer UPDATE
            DB::statement(
                'UPDATE si_solm.dbo.DJ2026_PERSONAL 
                SET NOMB_1 = s.NOMB_1, NOMB_2 = s.NOMB_2, APEL_1 = s.APEL_1, APEL_2 = s.APEL_2,
                    PERS_EMAIL = s.PERS_EMAIL, PERS_TELEFONO = s.PERS_TELEFONO, PERS_WHATSAPP = s.PERS_WHATSAPP,
                    DIRECCION = s.DIRECCION, PERS_SEXO = s.PERS_SEXO, SEXO = s.SEXO,
                    ESCI_CODIGO = s.ESCI_CODIGO, PERS_SNADAR = s.PERS_SNADAR,
                    tipo_sangr = s.tipo_sangr, peso_kilo = s.peso_kilo, tall_metr = s.tall_metr,
                    CODI_SIST_PENS = s.CODI_SIST_PENS, ESSALUD = s.ESSALUD, PERS_PENSIONISTA = s.PERS_PENSIONISTA,
                    PERS_EMBARGO = s.PERS_EMBARGO, PERS_CONSMO = s.PERS_CONSMO,
                    PERS_DEPT_ACT = s.PERS_DEPT_ACT, PERS_PROV_ACT = s.PERS_PROV_ACT, PERS_DIST_ACT = s.PERS_DIST_ACT,
                    PERS_DPTO_DIRDNI = s.PERS_DPTO_DIRDNI, PERS_PROV_DIRDNI = s.PERS_PROV_DIRDNI, PERS_DIST_DIRDNI = s.PERS_DIST_DIRDNI,
                    PERS_DIREC_DNI = s.PERS_DIREC_DNI, PERS_GRADO_INSTRUCCION = s.PERS_GRADO_INSTRUCCION,
                    CARR_CODIGO = s.CARR_CODIGO, IEDU_CODIGO = s.IEDU_CODIGO, EGRESO_EDUCATIVO = s.EGRESO_EDUCATIVO,
                    PERS_CONDISCAMEC = s.PERS_CONDISCAMEC, PERS_NRODISCAMEC = s.PERS_NRODISCAMEC,
                    PERS_LUGARSMO = s.PERS_LUGARSMO,
                    PERS_CONLICARMAS = s.PERS_CONLICARMAS, PERS_TIPOARMA = s.PERS_TIPOARMA, PERS_CONARMAS = s.PERS_CONARMAS,
                    PERS_BREVETE = s.PERS_BREVETE, CLASE_BREVETE = s.CLASE_BREVETE,
                    PERS_TIPO_VEHICULO = s.PERS_TIPO_VEHICULO, PERS_VEHICULO_PROPIO = s.PERS_VEHICULO_PROPIO,
                    PERS_NOMCONTACTO = s.PERS_NOMCONTACTO, PERS_NROEMERGENCIA = s.PERS_NROEMERGENCIA,
                    PERS_EMERC_FAMILIAR = s.PERS_EMERC_FAMILIAR, 
                 
                    
                    PERS_CTRABANT = s.PERS_CTRABANT,
                    PERS_CARGOTRABANT = s.PERS_CARGOTRABANT, PERS_DURACIONANT = s.PERS_DURACIONANT,
                    dj2026_banco = s.dj2026_banco, NACIONALIDAD = s.NACIONALIDAD,
                    dj2026_ocupacion_principal = s.dj2026_ocupacion_principal, dj2026_experiencia_anios = s.dj2026_experiencia_anios,
                    dj2026_familiar_empresa = s.dj2026_familiar_empresa, dj2026_familiar_nombre = s.dj2026_familiar_nombre,
                    dj2026_familiar_parentesco = s.dj2026_familiar_parentesco, dj2026_cantprofesion = s.dj2026_cantprofesion
                FROM sisolm_web.dbo.sw_MIGRA_PERSONAL s
                WHERE si_solm.dbo.DJ2026_PERSONAL.CODI_PERS = s.CODI_PERS 
                AND s.CODI_PERS = ?',
                [$codiPers]
            );
        } else {
            // No existe, hacer INSERT completo
            DB::statement(
                'INSERT INTO si_solm.dbo.DJ2026_PERSONAL
                SELECT * FROM sisolm_web.dbo.sw_MIGRA_PERSONAL WHERE CODI_PERS = ?',
                [$codiPers]
            );
        }
    }

    private function saveFamiliares($codiPers, $data)
    {
        // ✅ Validar que existan los arrays
        if (!isset($data['FAM_NOMBRES']) || !is_array($data['FAM_NOMBRES'])) {
            \Log::info('No hay familiares para guardar');

            return;
        }

        // Limpiar registros previos
        DB::delete(
            'DELETE FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE WHERE CODI_PERS = ?',
            [$codiPers]
        );

        // Insertar nuevos
        foreach ($data['FAM_NOMBRES'] as $index => $nombreCompleto) {
            if (empty($nombreCompleto)) {
                continue;
            }

            $parentesco = strtoupper(trim($data['FAM_PARENTESCO'][$index] ?? ''));
            // En Datos Familiares solo se registra fecha de nacimiento para hijos.
            $fechaNaci = preg_match('/^HIJ/i', $parentesco)
                ? ($data['FAM_FECHA_NACI'][$index] ?? null)
                : null;

            if (! preg_match('/^[\p{L}\p{M}]+(?:[\s.,\'’-]+[\p{L}\p{M}]+)*$/u', trim($nombreCompleto))) {
                throw new \InvalidArgumentException('Revisa el nombre del familiar: usa letras y separadores válidos.');
            }

            // ✅ Split mejorado del nombre completo
            // Formato esperado: "APELLIDO1 APELLIDO2, NOMBRE1 NOMBRE2"
            $nombreCompleto = trim($nombreCompleto);

            // Si tiene coma, dividir por coma
            if (strpos($nombreCompleto, ',') !== false) {
                [$apellidos, $nombres] = explode(',', $nombreCompleto, 2);
                $apellidos = trim($apellidos);
                $nombres = trim($nombres);

                $apellidosParts = preg_split('/\s+/', $apellidos, 2);
                $nombresParts = preg_split('/\s+/', $nombres, 2);

                $apel1 = $apellidosParts[0] ?? '';
                $apel2 = $apellidosParts[1] ?? '';
                $nomb1 = $nombresParts[0] ?? '';
                $nomb2 = $nombresParts[1] ?? '';
            } else {
                // Sin coma, asumir: APEL1 APEL2 NOMB1 NOMB2
                $nameParts = preg_split('/\s+/', $nombreCompleto);
                $apel1 = $nameParts[0] ?? '';
                $apel2 = $nameParts[1] ?? '';
                $nomb1 = $nameParts[2] ?? '';
                $nomb2 = isset($nameParts[3]) ? implode(' ', array_slice($nameParts, 3)) : '';
            }

            // DB::insert(
            //     "INSERT INTO sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE
            //     (CODI_PERS, TIPO_RELA, APEL_1, APEL_2, NOMB_1, NOMB_2, FECH_NACI)
            //     VALUES (?, ?, ?, ?, ?, ?, ?)",
            //     [$codiPers, $parentesco, $apel1, $apel2, $nomb1, $nomb2, $fechaNaci]
            // );

            DB::insert(
                'INSERT INTO si_solm.dbo.DERECHO_HABIENTE 
            (CODI_PERS, TIPO_RELA, APEL_1, APEL_2, NOMB_1, NOMB_2, FECH_NACI) 
            VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$codiPers, $parentesco, $apel1, $apel2, $nomb1, $nomb2, $fechaNaci]
            );
        }
    }

    // private function migrarFamiliares($codiPers)
    // {
    //     // Eliminar todos los familiares existentes del trabajador en DJ2026_DERECHO_HABIENTE
    //     DB::delete(
    //         'DELETE FROM si_solm.dbo.DERECHO_HABIENTE
    //      WHERE CODI_PERS = ?',
    //         [$codiPers]
    //     );

    //     // Insertar familiares en DJ2026_DERECHO_HABIENTE excluyendo la columna identity CODI_DERE_HABI
    //     DB::statement(
    //         'INSERT INTO si_solm.dbo.DERECHO_HABIENTE
    //     (
        
    //         CODI_PERS,
    //         TIPO_RELA,
    //         NOMB_1,
    //         NOMB_2,
    //         APEL_1,
    //         APEL_2,
    //         CODI_TIPO_DOCU,
    //         NRO_DOCU_IDEN,
    //         FECH_NACI,
    //         FALLECIDO,
    //         DEHA_OCUPACION,
    //         DEHA_EDAD,
    //         USUA_CODIGO_REG,
    //         USUA_FECHA_REG,
    //         USUA_CODIGO_MOD,
    //         USUA_FECHA_MOD,
    //         DEHA_SEXO,
            
    
    //         DEHA_MES_CONCEPCION,
    //         DEHA_FECHA_ALTA,
    //         DEHA_TIPO_BAJA,
    //         DEHA_FECHA_BAJA,
    //         DEHA_INCAPACIDAD,
    //         DEHA_RESOL_INCAPACIDAD,
    //         DEHA_VIGENCIA,
    //         DEHA_telefono,
    //         domicilio,
    //         DEHA_DEREHABI,
    //         TIDV_CODIGO
    //     )
    //     SELECT
       
    //         CODI_PERS,
    //         TIPO_RELA,
    //         NOMB_1,
    //         NOMB_2,
    //         APEL_1,
    //         APEL_2,
    //         CODI_TIPO_DOCU,
    //         NRO_DOCU_IDEN,
    //         FECH_NACI,
    //         FALLECIDO,
    //         DEHA_OCUPACION,
    //         DEHA_EDAD,
    //         USUA_CODIGO_REG,
    //         GETDATE(),
    //         USUA_CODIGO_MOD,
    //         GETDATE(),
    //         DEHA_SEXO,
           
       
    //         DEHA_MES_CONCEPCION,
    //         DEHA_FECHA_ALTA,
    //         DEHA_TIPO_BAJA,
    //         DEHA_FECHA_BAJA,
    //         DEHA_INCAPACIDAD,
    //         DEHA_RESOL_INCAPACIDAD,
    //         DEHA_VIGENCIA,
    //         DEHA_telefono,
    //         domicilio,
    //         DEHA_DEREHABI,
    //         TIDV_CODIGO
    //     FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE
    //     WHERE CODI_PERS = ?',
    //         [$codiPers]
    //     );

    //     // Eliminar todos los familiares existentes del trabajador en DERECHO_HABIENTE
    //     DB::delete(
    //         'DELETE FROM si_solm.dbo.DERECHO_HABIENTE
    //      WHERE CODI_PERS = ?',
    //         [$codiPers]
    //     );

    //     // Insertar familiares en DERECHO_HABIENTE excluyendo la columna identity CODI_DERE_HABI
    //     DB::statement("
    //     INSERT INTO si_solm.dbo.DERECHO_HABIENTE
    //     (
    //         CODI_PERS,
    //         TIPO_RELA,
    //         NOMB_1,
    //         NOMB_2,
    //         APEL_1,
    //         APEL_2,
    //         CODI_TIPO_DOCU,
    //         NRO_DOCU_IDEN,
    //         FECH_NACI,
    //         FALLECIDO,
    //         DEHA_OCUPACION,
    //         DEHA_EDAD,
    //         USUA_CODIGO_REG,
    //         USUA_FECHA_REG,
    //         USUA_CODIGO_MOD,
    //         USUA_FECHA_MOD,
    //         DEHA_SEXO,
    //         DEHA_MES_CONCEPCION,
    //         DEHA_FECHA_ALTA,
    //         DEHA_TIPO_BAJA,
    //         DEHA_FECHA_BAJA,
    //         DEHA_INCAPACIDAD,
    //         DEHA_RESOL_INCAPACIDAD,
    //         DEHA_VIGENCIA,
    //         DEHA_telefono,
    //         domicilio,
    //         DEHA_DEREHABI,
    //         TIDV_CODIGO
    //     )
    //     SELECT
    //         CODI_PERS,
    //         TIPO_RELA,
    //         NOMB_1,
    //         NOMB_2,
    //         APEL_1,
    //         APEL_2,
    //         CODI_TIPO_DOCU,
    //         NRO_DOCU_IDEN,
    //         TRY_CONVERT(datetime, NULLIF(FECH_NACI, ''), 103),
    //         FALLECIDO,
    //         DEHA_OCUPACION,
    //         DEHA_EDAD,
    //         USUA_CODIGO_REG,
    //         GETDATE(),
    //         USUA_CODIGO_MOD,
    //         GETDATE(),
    //         DEHA_SEXO,
    //         DEHA_MES_CONCEPCION,
    //         TRY_CONVERT(datetime, NULLIF(DEHA_FECHA_ALTA, ''), 103),
    //         DEHA_TIPO_BAJA,
    //         TRY_CONVERT(datetime, NULLIF(DEHA_FECHA_BAJA, ''), 103),
    //         DEHA_INCAPACIDAD,
    //         DEHA_RESOL_INCAPACIDAD,
    //         TRY_CONVERT(datetime, NULLIF(DEHA_VIGENCIA, ''), 103),
    //         DEHA_telefono,
    //         domicilio,
    //         DEHA_DEREHABI,
    //         TIDV_CODIGO
    //     FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE
    //     WHERE CODI_PERS = ?
    // ", [$codiPers]);
    // }


    private function migrarFamiliares($codiPers)
    {
        // Función SQL reutilizable como expresión CASE para convertir cualquier formato
        // Orden: YYYY-MM-DD (120) → DD/MM/YYYY (103) → MM/DD/YYYY (101) → si nada funciona → NULL

        // $convertFecha = "
        //     CASE
        //         WHEN NULLIF({col}, '') IS NULL THEN NULL
        //         WHEN TRY_CONVERT(datetime, NULLIF({col}, ''), 120) IS NOT NULL THEN TRY_CONVERT(datetime, NULLIF({col}, ''), 120)
        //         WHEN TRY_CONVERT(datetime, NULLIF({col}, ''), 103) IS NOT NULL THEN TRY_CONVERT(datetime, NULLIF({col}, ''), 103)
        //         WHEN TRY_CONVERT(datetime, NULLIF({col}, ''), 101) IS NOT NULL THEN TRY_CONVERT(datetime, NULLIF({col}, ''), 101)
        //         WHEN TRY_CONVERT(datetime, NULLIF({col}, ''), 104) IS NOT NULL THEN TRY_CONVERT(datetime, NULLIF({col}, ''), 104)
        //         ELSE NULL
        //     END
        // ";

        $toDate = function($col) {
            return "TRY_CONVERT(datetime, NULLIF(LTRIM(RTRIM({$col})), ''), 120)";
        };

        // Eliminar todos los familiares existentes del trabajador en DERECHO_HABIENTExd
        DB::delete(
            'DELETE FROM si_solm.dbo.DERECHO_HABIENTE WHERE CODI_PERS = ?',
            [$codiPers]
        );

        // Insertar familiares en DERECHO_HABIENTExd
        DB::statement("
            INSERT INTO si_solm.dbo.DERECHO_HABIENTE
            (
                CODI_PERS, TIPO_RELA, NOMB_1, NOMB_2, APEL_1, APEL_2,
                CODI_TIPO_DOCU, NRO_DOCU_IDEN, FECH_NACI, FALLECIDO,
                DEHA_OCUPACION, DEHA_EDAD, USUA_CODIGO_REG, USUA_FECHA_REG,
                USUA_CODIGO_MOD, USUA_FECHA_MOD, DEHA_SEXO, DEHA_MES_CONCEPCION,
                DEHA_FECHA_ALTA, DEHA_TIPO_BAJA, DEHA_FECHA_BAJA, DEHA_INCAPACIDAD,
                DEHA_RESOL_INCAPACIDAD, DEHA_VIGENCIA, DEHA_telefono, domicilio,
                DEHA_DEREHABI, TIDV_CODIGO
            )
            SELECT
                CODI_PERS, TIPO_RELA, NOMB_1, NOMB_2, APEL_1, APEL_2,
                CODI_TIPO_DOCU, NRO_DOCU_IDEN,
                {$toDate('FECH_NACI')},
                0, DEHA_OCUPACION, DEHA_EDAD,
                USUA_CODIGO_REG, GETDATE(), USUA_CODIGO_MOD, GETDATE(),
                DEHA_SEXO, DEHA_MES_CONCEPCION,
                {$toDate('DEHA_FECHA_ALTA')},
                DEHA_TIPO_BAJA,
                {$toDate('DEHA_FECHA_BAJA')},
                DEHA_INCAPACIDAD, DEHA_RESOL_INCAPACIDAD,
                'SI', -- DEHA_VIGENCIA: 'SI' predeterminado
                DEHA_telefono, domicilio, DEHA_DEREHABI, TIDV_CODIGO
            FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE
            WHERE CODI_PERS = ?
        ", [$codiPers]);

        // Eliminar todos los familiares existentes del trabajador en DERECHO_HABIENTE
        DB::delete(
            'DELETE FROM si_solm.dbo.DERECHO_HABIENTE WHERE CODI_PERS = ?',
            [$codiPers]
        );

        // Insertar familiares en DERECHO_HABIENTE
        DB::statement("
            INSERT INTO si_solm.dbo.DERECHO_HABIENTE
            (
                CODI_PERS, TIPO_RELA, NOMB_1, NOMB_2, APEL_1, APEL_2,
                CODI_TIPO_DOCU, NRO_DOCU_IDEN, FECH_NACI, FALLECIDO,
                DEHA_OCUPACION, DEHA_EDAD, USUA_CODIGO_REG, USUA_FECHA_REG,
                USUA_CODIGO_MOD, USUA_FECHA_MOD, DEHA_SEXO, DEHA_MES_CONCEPCION,
                DEHA_FECHA_ALTA, DEHA_TIPO_BAJA, DEHA_FECHA_BAJA, DEHA_INCAPACIDAD,
                DEHA_RESOL_INCAPACIDAD, DEHA_VIGENCIA, DEHA_telefono, domicilio,
                DEHA_DEREHABI, TIDV_CODIGO
            )
            SELECT
                CODI_PERS, TIPO_RELA, NOMB_1, NOMB_2, APEL_1, APEL_2,
                CODI_TIPO_DOCU, NRO_DOCU_IDEN,
                {$toDate('FECH_NACI')},
                0, DEHA_OCUPACION, DEHA_EDAD,
                USUA_CODIGO_REG, GETDATE(), USUA_CODIGO_MOD, GETDATE(),
                DEHA_SEXO, DEHA_MES_CONCEPCION,
                {$toDate('DEHA_FECHA_ALTA')},
                DEHA_TIPO_BAJA,
                {$toDate('DEHA_FECHA_BAJA')},
                DEHA_INCAPACIDAD, DEHA_RESOL_INCAPACIDAD,
                'SI', -- DEHA_VIGENCIA: 'SI' predeterminado
                DEHA_telefono, domicilio, DEHA_DEREHABI, TIDV_CODIGO
            FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE
            WHERE CODI_PERS = ?
        ", [$codiPers]);
    }




private function migrarFamiliares_solo_nuevo($codiPers)
{
    // ── 1. Limpiar DERECHO_HABIENTE ──────────────────────────
    DB::delete(
        'DELETE FROM si_solm.dbo.DERECHO_HABIENTE WHERE CODI_PERS = ?',
        [$codiPers]
    );

    // ── 2. INSERT con TRY_CONVERT en TODOS los campos fecha ──
    //    (antes el primer bloque copiaba FECH_NACI sin conversión → error)
    DB::statement("
        INSERT INTO si_solm.dbo.DERECHO_HABIENTE (
            CODI_PERS,
            TIPO_RELA,
            NOMB_1,
            NOMB_2,
            APEL_1,
            APEL_2,
            CODI_TIPO_DOCU,
            NRO_DOCU_IDEN,
            FECH_NACI,
            FALLECIDO,
            DEHA_OCUPACION,
            DEHA_EDAD,
            USUA_CODIGO_REG,
            USUA_FECHA_REG,
            USUA_CODIGO_MOD,
            USUA_FECHA_MOD,
            DEHA_SEXO,
            DEHA_MES_CONCEPCION,
            DEHA_FECHA_ALTA,
            DEHA_TIPO_BAJA,
            DEHA_FECHA_BAJA,
            DEHA_INCAPACIDAD,
            DEHA_RESOL_INCAPACIDAD,
            DEHA_VIGENCIA,
            DEHA_telefono,
            domicilio,
            DEHA_DEREHABI,
            TIDV_CODIGO
        )
        SELECT
            CODI_PERS,
            TIPO_RELA,
            NOMB_1,
            NOMB_2,
            APEL_1,
            APEL_2,
            CODI_TIPO_DOCU,
            NRO_DOCU_IDEN,
            TRY_CONVERT(datetime, NULLIF(LTRIM(RTRIM(FECH_NACI)), ''), 103),
            0,
            DEHA_OCUPACION,
            DEHA_EDAD,
            USUA_CODIGO_REG,
            GETDATE(),
            USUA_CODIGO_MOD,
            GETDATE(),
            DEHA_SEXO,
            DEHA_MES_CONCEPCION,
            TRY_CONVERT(datetime, NULLIF(LTRIM(RTRIM(DEHA_FECHA_ALTA)), ''), 103),
            DEHA_TIPO_BAJA,
            TRY_CONVERT(datetime, NULLIF(LTRIM(RTRIM(DEHA_FECHA_BAJA)), ''), 103),
            DEHA_INCAPACIDAD,
            DEHA_RESOL_INCAPACIDAD,
            'SI', -- DEHA_VIGENCIA: 'SI' predeterminado
            DEHA_telefono,
            domicilio,
            DEHA_DEREHABI,
            TIDV_CODIGO
        FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE
        WHERE CODI_PERS = ?
    ", [$codiPers]);

    Log::info('migrarFamiliares: familiares migrados a DERECHO_HABIENTE', [
        'CODI_PERS' => $codiPers,
    ]);
}
    // private function migrarFamiliares($codiPers)
    // {
    //     // Limpiar destino
    //     DB::delete(
    //         "DELETE TOP (1) FROM si_solm.dbo.DJ2026_DERECHO_HABIENTE WHERE CODI_PERS = ?",
    //         [$codiPers]
    //     );

    //     // Copiar de migración a DJ2026
    //     DB::statement(
    //         "INSERT INTO si_solm.dbo.DJ2026_DERECHO_HABIENTE
    //         SELECT * FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE WHERE CODI_PERS = ?",
    //         [$codiPers]
    //     );

    //     // DB::delete(
    //     //     "DELETE TOP (1) FROM si_solm.dbo.DERECHO_HABIENTE WHERE CODI_PERS = ?",
    //     //     [$codiPers]
    //     // );

    //     // DB::statement(
    //     //     "INSERT INTO si_solm.dbo.DERECHO_HABIENTE
    //     //     SELECT * FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE WHERE CODI_PERS = ?",
    //     //     [$codiPers]
    //     // );
    // }

    /**
     * Sincroniza familiares de una DJ existente usando baja lógica:
     * conserva filas eliminadas con DEHA_VIGENCIA='NO', reactiva las vigentes
     * y agrega solo las que aún no existen.
     */
    private function migrarFamiliaresConVigencia($codiPers, array $data)
    {
        $usuario = strtoupper(trim((string) (session('usuario') ?? '')));

        DB::update(
            "UPDATE si_solm.dbo.DERECHO_HABIENTE
             SET DEHA_VIGENCIA = 'NO', USUA_CODIGO_MOD = ?, USUA_FECHA_MOD = GETDATE()
             WHERE CODI_PERS = ?
               AND UPPER(LTRIM(RTRIM(ISNULL(DEHA_VIGENCIA, 'SI')))) = 'SI'",
            [$usuario ?: null, $codiPers]
        );

        $nombres = is_array($data['FAM_NOMBRES'] ?? null) ? $data['FAM_NOMBRES'] : [];
        $parentescos = is_array($data['FAM_PARENTESCO'] ?? null) ? $data['FAM_PARENTESCO'] : [];
        $fechas = is_array($data['FAM_FECHA_NACI'] ?? null) ? $data['FAM_FECHA_NACI'] : [];
        $ids = is_array($data['FAM_ID'] ?? null) ? $data['FAM_ID'] : [];

        // Las filas que ya existen conservan su identidad; se editan/reactivan
        // mediante su ID en vez de eliminarlas y generar otro registro.
        foreach ($nombres as $i => $nombreCompleto) {
            $nombreCompleto = trim((string) $nombreCompleto);
            $id = $ids[$i] ?? null;
            if ($nombreCompleto === '' || !$id || !ctype_digit((string) $id)) continue;

            [$apel1, $apel2, $nomb1, $nomb2] = $this->separarNombreFamiliar($nombreCompleto);
            $parentesco = strtoupper(trim((string) ($parentescos[$i] ?? '')));
            $fechaTexto = preg_match('/^HIJ/i', $parentesco) ? trim((string) ($fechas[$i] ?? '')) : '';
            $fecha = $fechaTexto !== ''
                ? ($this->parsearFechaNacimiento($fechaTexto)?->format('Ymd') ?? '')
                : '';

            DB::update(
                "UPDATE si_solm.dbo.DERECHO_HABIENTE
                 SET TIPO_RELA = ?, APEL_1 = ?, APEL_2 = ?, NOMB_1 = ?, NOMB_2 = ?,
                     FECH_NACI = CASE WHEN NULLIF(?, '') IS NULL THEN FECH_NACI
                                      ELSE TRY_CONVERT(datetime, NULLIF(?, ''), 112) END,
                     DEHA_VIGENCIA = 'SI', USUA_CODIGO_MOD = ?, USUA_FECHA_MOD = GETDATE()
                 WHERE CODI_DERE_HABI = ? AND CODI_PERS = ?",
                [$parentesco, $apel1, $apel2, $nomb1, $nomb2, $fecha, $fecha, $usuario ?: null, $id, $codiPers]
            );
        }

        // Compatibilidad con registros que vienen de una tabla intermedia y no
        // tienen ID: la fecha no forma parte de la identidad, porque justamente
        // puede ser el dato que el usuario está corrigiendo.
        // Las tablas están en bases con collations distintas. Normalizamos cada
        // comparación de texto al collation de la base de conexión para evitar
        // el conflicto Modern_Spanish_CI_AS vs SQL_Latin1_General_CP1_CI_AS.
        $coincidencia = "(d.CODI_PERS COLLATE DATABASE_DEFAULT) = (s.CODI_PERS COLLATE DATABASE_DEFAULT)
            AND (ISNULL(LTRIM(RTRIM(d.TIPO_RELA)), '') COLLATE DATABASE_DEFAULT) = (ISNULL(LTRIM(RTRIM(s.TIPO_RELA)), '') COLLATE DATABASE_DEFAULT)
            AND (ISNULL(LTRIM(RTRIM(d.APEL_1)), '') COLLATE DATABASE_DEFAULT) = (ISNULL(LTRIM(RTRIM(s.APEL_1)), '') COLLATE DATABASE_DEFAULT)
            AND (ISNULL(LTRIM(RTRIM(d.APEL_2)), '') COLLATE DATABASE_DEFAULT) = (ISNULL(LTRIM(RTRIM(s.APEL_2)), '') COLLATE DATABASE_DEFAULT)
            AND (ISNULL(LTRIM(RTRIM(d.NOMB_1)), '') COLLATE DATABASE_DEFAULT) = (ISNULL(LTRIM(RTRIM(s.NOMB_1)), '') COLLATE DATABASE_DEFAULT)
            AND (ISNULL(LTRIM(RTRIM(d.NOMB_2)), '') COLLATE DATABASE_DEFAULT) = (ISNULL(LTRIM(RTRIM(s.NOMB_2)), '') COLLATE DATABASE_DEFAULT)";

        DB::update(
            "UPDATE d
             SET TIPO_RELA = s.TIPO_RELA,
                 APEL_1 = s.APEL_1,
                 APEL_2 = s.APEL_2,
                 NOMB_1 = s.NOMB_1,
                 NOMB_2 = s.NOMB_2,
                 FECH_NACI = COALESCE(TRY_CONVERT(datetime, s.FECH_NACI), d.FECH_NACI),
                 DEHA_VIGENCIA = 'SI',
                 USUA_CODIGO_MOD = ?,
                 USUA_FECHA_MOD = GETDATE()
             FROM si_solm.dbo.DERECHO_HABIENTE d
             INNER JOIN sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE s ON {$coincidencia}
             WHERE d.CODI_PERS = ?",
            [$usuario ?: null, $codiPers]
        );

        $toDate = fn ($col) => "TRY_CONVERT(datetime, NULLIF(LTRIM(RTRIM({$col})), ''), 120)";
        DB::statement("INSERT INTO si_solm.dbo.DERECHO_HABIENTE
            (CODI_PERS, TIPO_RELA, NOMB_1, NOMB_2, APEL_1, APEL_2,
             CODI_TIPO_DOCU, NRO_DOCU_IDEN, FECH_NACI, FALLECIDO,
             DEHA_OCUPACION, DEHA_EDAD, USUA_CODIGO_REG, USUA_FECHA_REG,
             USUA_CODIGO_MOD, USUA_FECHA_MOD, DEHA_SEXO, DEHA_MES_CONCEPCION,
             DEHA_FECHA_ALTA, DEHA_TIPO_BAJA, DEHA_FECHA_BAJA, DEHA_INCAPACIDAD,
             DEHA_RESOL_INCAPACIDAD, DEHA_VIGENCIA, DEHA_telefono, domicilio,
             DEHA_DEREHABI, TIDV_CODIGO)
            SELECT s.CODI_PERS, s.TIPO_RELA, s.NOMB_1, s.NOMB_2, s.APEL_1, s.APEL_2,
                   s.CODI_TIPO_DOCU, s.NRO_DOCU_IDEN, {$toDate('s.FECH_NACI')}, 0,
                   s.DEHA_OCUPACION, s.DEHA_EDAD, s.USUA_CODIGO_REG, GETDATE(),
                   ?, GETDATE(), s.DEHA_SEXO, s.DEHA_MES_CONCEPCION,
                   {$toDate('s.DEHA_FECHA_ALTA')}, s.DEHA_TIPO_BAJA,
                   {$toDate('s.DEHA_FECHA_BAJA')}, s.DEHA_INCAPACIDAD,
                   s.DEHA_RESOL_INCAPACIDAD, 'SI', s.DEHA_telefono, s.domicilio,
                   s.DEHA_DEREHABI, s.TIDV_CODIGO
            FROM sisolm_web.dbo.sw_MIGRA_DERECHO_HABIENTE s
            WHERE s.CODI_PERS = ?
              AND NOT EXISTS (
                  SELECT 1 FROM si_solm.dbo.DERECHO_HABIENTE d WHERE {$coincidencia}
              )", [$usuario ?: null, $codiPers]);
    }

    /**
     * Reaplica y verifica las fechas de hijos directamente en DERECHO_HABIENTE.
     * La sincronización desde la tabla temporal no debe borrar una fecha que ya
     * se escribió en la fila cuando la copia temporal llegó vacía.
     */
    private function asegurarFechasNacimientoHijosGuardadas($codiPers, array $data): void
    {
        $nombres = is_array($data['FAM_NOMBRES'] ?? null) ? $data['FAM_NOMBRES'] : [];
        $parentescos = is_array($data['FAM_PARENTESCO'] ?? null) ? $data['FAM_PARENTESCO'] : [];
        $fechas = is_array($data['FAM_FECHA_NACI'] ?? null) ? $data['FAM_FECHA_NACI'] : [];
        $ids = is_array($data['FAM_ID'] ?? null) ? $data['FAM_ID'] : [];

        foreach ($nombres as $i => $nombreCompleto) {
            $parentesco = mb_strtoupper(trim((string) ($parentescos[$i] ?? '')), 'UTF-8');
            if (!str_starts_with($parentesco, 'HIJO')) continue;

            $fechaTexto = trim((string) ($fechas[$i] ?? ''));
            if ($fechaTexto === '') continue;

            $fecha = $this->parsearFechaNacimiento($fechaTexto);
            if (!$fecha) {
                throw new \InvalidArgumentException('La fecha de nacimiento de un hijo tiene un formato inválido.');
            }
            $nombreCompleto = trim((string) $nombreCompleto);
            if ($nombreCompleto === '') {
                throw new \InvalidArgumentException('Falta el nombre del hijo cuya fecha de nacimiento se intenta guardar.');
            }
            // Estilo 112 (YYYYMMDD) evita que SQL Server interprete la fecha
            // como NULL según el idioma o DATEFORMAT de la sesión.
            $fechaSql = $fecha->format('Ymd');
            $id = trim((string) ($ids[$i] ?? ''));
            $guardado = false;

            if ($id !== '' && ctype_digit($id)) {
                $filasActualizadas = DB::update(
                    "UPDATE si_solm.dbo.DERECHO_HABIENTE
                     SET FECH_NACI = TRY_CONVERT(datetime, ?, 112)
                     WHERE CODI_DERE_HABI = ? AND CODI_PERS = ?",
                    [$fechaSql, $id, $codiPers]
                );
                $guardado = $filasActualizadas > 0 && (bool) DB::selectOne(
                    "SELECT TOP 1 1 AS existe
                     FROM si_solm.dbo.DERECHO_HABIENTE
                     WHERE CODI_DERE_HABI = ? AND CODI_PERS = ?
                       AND CONVERT(date, FECH_NACI) = TRY_CONVERT(date, ?, 112)",
                    [$id, $codiPers, $fechaSql]
                );
            }

            if (!$guardado) {
                [$apel1, $apel2, $nomb1, $nomb2] = $this->separarNombreFamiliar($nombreCompleto);
                $filasActualizadas = DB::update(
                    "UPDATE TOP (1) dh
                     SET FECH_NACI = TRY_CONVERT(datetime, ?, 112)
                     FROM si_solm.dbo.DERECHO_HABIENTE dh
                     WHERE dh.CODI_PERS = ?
                       AND UPPER(LTRIM(RTRIM(dh.TIPO_RELA))) LIKE 'HIJO%'
                       AND UPPER(LTRIM(RTRIM(ISNULL(dh.APEL_1, '')))) = UPPER(?)
                       AND UPPER(LTRIM(RTRIM(ISNULL(dh.APEL_2, '')))) = UPPER(?)
                       AND UPPER(LTRIM(RTRIM(ISNULL(dh.NOMB_1, '')))) = UPPER(?)
                       AND UPPER(LTRIM(RTRIM(ISNULL(dh.NOMB_2, '')))) = UPPER(?)",
                    [$fechaSql, $codiPers, $apel1, $apel2, $nomb1, $nomb2]
                );
                $guardado = $filasActualizadas > 0 && (bool) DB::selectOne(
                    "SELECT TOP 1 1 AS existe
                     FROM si_solm.dbo.DERECHO_HABIENTE
                     WHERE CODI_PERS = ? AND UPPER(LTRIM(RTRIM(TIPO_RELA))) LIKE 'HIJO%'
                       AND UPPER(LTRIM(RTRIM(ISNULL(APEL_1, '')))) = UPPER(?)
                       AND UPPER(LTRIM(RTRIM(ISNULL(APEL_2, '')))) = UPPER(?)
                       AND UPPER(LTRIM(RTRIM(ISNULL(NOMB_1, '')))) = UPPER(?)
                       AND UPPER(LTRIM(RTRIM(ISNULL(NOMB_2, '')))) = UPPER(?)
                       AND CONVERT(date, FECH_NACI) = TRY_CONVERT(date, ?, 112)",
                    [$codiPers, $apel1, $apel2, $nomb1, $nomb2, $fechaSql]
                );
            }

            if (!$guardado) {
                throw new \RuntimeException('No se pudo verificar la fecha de nacimiento de un hijo en DERECHO_HABIENTE; la DJ no se guardó.');
            }
        }
    }

    private function separarNombreFamiliar(string $nombreCompleto): array
    {
        $apel1 = $apel2 = $nomb1 = $nomb2 = '';
        if (strpos($nombreCompleto, ',') !== false) {
            [$apellidos, $nombres] = explode(',', $nombreCompleto, 2);
            $apellidosPartes = preg_split('/\s+/', trim($apellidos), 2);
            $nombresPartes = preg_split('/\s+/', trim($nombres), 2);
            $apel1 = $apellidosPartes[0] ?? '';
            $apel2 = $apellidosPartes[1] ?? '';
            $nomb1 = $nombresPartes[0] ?? '';
            $nomb2 = $nombresPartes[1] ?? '';
        } else {
            $partes = preg_split('/\s+/', trim($nombreCompleto));
            $apel1 = $partes[0] ?? '';
            $apel2 = $partes[1] ?? '';
            $nomb1 = $partes[2] ?? '';
            $nomb2 = isset($partes[3]) ? implode(' ', array_slice($partes, 3)) : '';
        }
        return [$apel1, $apel2, $nomb1, $nomb2];
    }

    private function saveOcupaciones($dni, $data)
    {
        // ✅ Cambiar el nombre del campo esperado
        if (!isset($data['dj2026_descripcion']) || !is_array($data['dj2026_descripcion'])) {
            \Log::info('No hay ocupaciones para guardar');

            return;
        }

        // Limpiar previas
        DB::delete(
            'DELETE FROM sisolm_web.dbo.dj2026_OCUPACIONES WHERE PERS_DNI = ?',
            [$dni]
        );

        // Insertar nuevas (máximo 2)
        $ocupaciones = array_filter($data['dj2026_descripcion']);
        $ocupaciones = array_slice($ocupaciones, 0, 2);

        foreach ($ocupaciones as $desc) {
            DB::insert(
                'INSERT INTO sisolm_web.dbo.dj2026_OCUPACIONES (PERS_DNI, dj2026_descripcion) VALUES (?, ?)',
                [$dni, strtoupper(trim($desc))]
            );
        }
    }

    private function migrarOcupaciones($dni)
    {
        // Limpiar destino
        DB::delete(
            'DELETE FROM si_solm.dbo.DJ2026_OCUPACIONES_PER WHERE PERS_DNI = ?',
            [$dni]
        );

        // Copiar
        DB::statement(
            'INSERT INTO si_solm.dbo.DJ2026_OCUPACIONES_PER (PERS_DNI, dj2026_descripcion)
            SELECT PERS_DNI, dj2026_descripcion FROM sisolm_web.dbo.dj2026_OCUPACIONES WHERE PERS_DNI = ?',
            [$dni]
        );
    }

    private function formatDatesForInput($data)
    {
        // Formatear fechas
        $dateFields = ['FECH_NACI', 'PERS_FECHCADUCADNI', 'FECH_INGRE', 'FECH_CESE'];

        foreach ($dateFields as $field) {
            if (isset($data[$field])) {
                if ($data[$field] instanceof \DateTime) {
                    $data[$field] = $data[$field]->format('Y-m-d');
                } elseif (is_string($data[$field]) && strpos($data[$field], ' ') !== false) {
                    $data[$field] = explode(' ', $data[$field])[0];
                }
            }
        }

        // ✅ Limpiar campos numéricos (quitar texto, dejar solo números)
        $numericFields = ['dj2026_experiencia_anios', 'peso_kilo', 'tall_metr', 'EGRESO_EDUCATIVO'];

        foreach ($numericFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                // Extraer solo números (y punto decimal si existe)
                $data[$field] = preg_replace('/[^0-9.]/', '', $data[$field]);
            }
        }

        return $data;
    }

    private function groupFamiliares($familiares)
    {
        $grouped = [
            'padres' => [],
            'madre' => [],
            'hijos' => [],
            'conyugue' => [],
            'otros' => [],
        ];

        foreach ($familiares as $f) {
            $f = (array) $f;
            $tipo = strtoupper(trim((string) ($f['TIPO_RELA'] ?? '')));

            // Catálogo TIPO_VINCULO_FAMILIAR + valores legados
            if (str_starts_with($tipo, 'HIJO')) {
                $grouped['hijos'][] = $f;
            } elseif (in_array($tipo, ['CONYUGE', 'CONYUGUE', 'CONVIVIENTE', 'GESTANTE'], true)) {
                $grouped['conyugue'][] = $f;
            } elseif (in_array($tipo, ['PADRE', 'MADRE', 'HERMANO', 'HERMANA'], true)) {
                $grouped['padres'][] = $f;
            } else {
                // Legados u otros (ABUELO, TIO, PRIMO, OTRO, AMISTAD...) — no se pierden
                $grouped['otros'][] = $f;
            }
        }

        return $grouped;
    }

    public function proxyFoto(Request $request)
    {
        $codiPers = $request->get('codi_pers');
        if (!$codiPers || !preg_match('/^[a-zA-Z0-9]+$/', $codiPers)) {
            return response()->json(['success' => false], 400);
        }

        $url = "http://190.116.178.163/Biblioteca_Grafica/Fotos/{$codiPers}.jpg";

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            // Ejecutamos la petición y obtenemos la información (Faltaba en tu código comentado)
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($httpCode !== 200 || empty($imageData)) {
                return response()->json(['success' => false, 'message' => 'Imagen no encontrada'], 404);
            }

            $base64 = 'data:' . ($contentType ?: 'image/jpeg') . ';base64,' . base64_encode($imageData);

            return response()->json([
                'success' => true,
                'base64' => $base64,
            ]);
        } catch (\Exception $e) {
            Log::error('Error proxy foto: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener imagen'], 500);
        }
    }

    private function usuarioPuedeRevisarSolicitudSip(): bool
    {
        if ((int) (session('tipo_rol') ?? 0) === 1) return true;
        foreach ((array) session('permisos', []) as $menu) {
            if (($menu['modulo'] ?? '') !== 'dj') continue;
            foreach (($menu['submenus'] ?? []) as $submenu) {
                if (($submenu['vista'] ?? '') === 'actualizar_dj') return true;
            }
        }
        return false;
    }

    private function usuarioPuedeResolverSolicitudSip(): bool
    {
        // Códigos confirmados en sisolm_web.dbo.sw_roles:
        // 11 = Super Usuario, 17 = Admins RRHH.
        return in_array((int) session('tipo_rol', 0), [11, self::ROL_ADMIN_RRHH], true);
    }

    public function detalleSolicitudSip($id)
    {
        if (!$this->usuarioPuedeRevisarSolicitudSip()) {
            return response()->json(['success' => false, 'message' => 'No tiene permiso para revisar solicitudes DJ.'], 403);
        }

        $solicitud = DB::table('sisolm_web.dbo.sw_DJ_SOLICITUD')
            ->where('SOLI_CODIGO', $id)
            ->first();
        if (!$solicitud) return response()->json(['success' => false, 'message' => 'No se encontró la solicitud.'], 404);

        $detalles = DB::table('sisolm_web.dbo.sw_DJ_SOLICITUD_DETALLE')
            ->where('SOLI_CODIGO', $id)
            ->orderBy('SOLD_CODIGO')
            ->get()
            ->map(function ($fila) {
                $fila->SOLD_VALOR_ANTERIOR = json_decode($fila->SOLD_VALOR_ANTERIOR, true) ?? $fila->SOLD_VALOR_ANTERIOR;
                $fila->SOLD_VALOR_PROPUESTO = json_decode($fila->SOLD_VALOR_PROPUESTO, true) ?? $fila->SOLD_VALOR_PROPUESTO;
                return $fila;
            });

        $personal = DB::selectOne(
            'SELECT p.CODI_PERS, p.NRO_DOCU_IDEN, p.APEL_1, p.APEL_2, p.NOMB_1, p.NOMB_2,
                    p.SUCU_CODIGO, s.SUCU_ABREVIATURA, p.PERS_TIPOTRAB, tp.TIPE_DESCRIPCION,
                    p.CODI_CARG, c.DESC_CARGO
             FROM si_solm.dbo.PERSONAL p
             LEFT JOIN si_solm.dbo.SISO_SUCURSAL s ON s.SUCU_CODIGO = p.SUCU_CODIGO
             LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL tp ON tp.TIPE_CODIGO = p.PERS_TIPOTRAB
             LEFT JOIN si_solm.dbo.CARGOS c ON c.CODI_CARG = p.CODI_CARG
             WHERE p.CODI_PERS = ?',
            [$solicitud->CODI_PERS]
        );

        $familiaresActuales = DB::select(
            "SELECT TIPO_RELA,
                    ISNULL(APEL_1, '') + ' ' + ISNULL(APEL_2, '') + ' ' +
                    ISNULL(NOMB_1, '') + ' ' + ISNULL(NOMB_2, '') AS Nombres,
                    OCUPACION, CONVERT(CHAR(10), FECH_NACI, 103) AS FECH_NACI
             FROM si_solm.dbo.DERECHO_HABIENTE
             WHERE CODI_PERS = ?",
            [$solicitud->CODI_PERS]
        );

        return response()->json([
            'success' => true,
            'solicitud' => $solicitud,
            'personal' => $personal,
            'familiares_actuales' => $familiaresActuales,
            'detalles' => $detalles,
        ]);
    }

    public function resolverSolicitudSip(Request $request, $id)
    {
        if (!$this->usuarioPuedeResolverSolicitudSip()) {
            return response()->json(['success' => false, 'message' => 'Solo Admins RRHH y Super Usuario pueden aprobar o rechazar solicitudes DJ.'], 403);
        }

        $data = $request->validate([
            'decision' => 'required|in:APROBADA,RECHAZADA',
            'comentario' => 'nullable|string|max:1000',
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $solicitud = DB::table('sisolm_web.dbo.sw_DJ_SOLICITUD')
                    ->where('SOLI_CODIGO', $id)
                    ->lockForUpdate()
                    ->first();
                if (!$solicitud || $solicitud->SOLI_ESTADO !== 'PENDIENTE') {
                    throw new \RuntimeException('La solicitud ya fue atendida o no existe.');
                }

                if ($data['decision'] === 'APROBADA') {
                    if (!DB::table('si_solm.dbo.PERSONAL')->where('CODI_PERS', $solicitud->CODI_PERS)->exists()) {
                        throw new \RuntimeException('No se encontró el personal en SISOL.');
                    }
                    $detallesSolicitud = DB::table('sisolm_web.dbo.sw_DJ_SOLICITUD_DETALLE')
                        ->where('SOLI_CODIGO', $id)
                        ->orderBy('SOLD_CODIGO')
                        ->get(['SOLD_CAMPO', 'SOLD_VALOR_ANTERIOR', 'SOLD_VALOR_PROPUESTO']);
                    $propuesta = [];
                    foreach ($detallesSolicitud as $detalle) {
                        $campo = (string) $detalle->SOLD_CAMPO;
                        $anterior = $this->decodificarValorSolicitudCorreo($detalle->SOLD_VALOR_ANTERIOR);
                        $solicitado = $this->decodificarValorSolicitudCorreo($detalle->SOLD_VALOR_PROPUESTO);
                        if (in_array($campo, ['DJ_DATOS_ACTUALES', 'DJ_PROPUESTA_COMPLETA'], true)) continue;
                        if ($campo === 'DJ_FAMILIARES') {
                            if ($this->normalizarValorSolicitudCorreo($anterior, $campo) === $this->normalizarValorSolicitudCorreo($solicitado, $campo)) continue;
                            $propuesta['FAM_PARENTESCO'] = [];
                            $propuesta['FAM_NOMBRES'] = [];
                            $propuesta['FAM_FECHA_NACI'] = [];
                            foreach ((array) $solicitado as $familiar) {
                                if (!is_array($familiar) || trim((string) ($familiar['nombres'] ?? $familiar['Nombres'] ?? '')) === '') continue;
                                $propuesta['FAM_PARENTESCO'][] = $familiar['parentesco'] ?? $familiar['TIPO_RELA'] ?? '';
                                $propuesta['FAM_NOMBRES'][] = $familiar['nombres'] ?? $familiar['Nombres'] ?? '';
                                $propuesta['FAM_FECHA_NACI'][] = $familiar['fech_naci'] ?? $familiar['FECH_NACI'] ?? '';
                            }
                            continue;
                        }
                        if ($this->normalizarValorSolicitudCorreo($anterior, $campo) === $this->normalizarValorSolicitudCorreo($solicitado, $campo)) continue;
                        $propuesta[$campo] = $solicitado;
                    }
                    if (!$propuesta) throw new \RuntimeException('La solicitud no contiene cambios registrados.');
                    $this->aplicarPropuestaSipEnPersonal(
                        $solicitud->CODI_PERS,
                        $propuesta
                    );
                }

                DB::table('sisolm_web.dbo.sw_DJ_SOLICITUD')
                    ->where('SOLI_CODIGO', $id)
                    ->update([
                        'SOLI_ESTADO' => $data['decision'],
                        'SOLI_FECHA_REV' => DB::raw('GETDATE()'),
                        'SOLI_USUA_REV' => (string) (session('usuario') ?? 'SISOL'),
                        'SOLI_COMENTARIO_REV' => $data['comentario'] ?? null,
                        'SOLI_FECHA_APLICA' => $data['decision'] === 'APROBADA' ? DB::raw('GETDATE()') : null,
                    ]);
            });

            $mensaje = $data['decision'] === 'APROBADA' ? 'Solicitud aprobada y aplicada.' : 'Solicitud rechazada.';
            $correoEnviado = null;

            if ($data['decision'] === 'APROBADA') {
                $correoEnviado = false;
                try {
                    $personal = DB::selectOne(
                        'SELECT PERS_EMAIL, NRO_DOCU_IDEN, NOMB_1, NOMB_2, APEL_1, APEL_2
                         FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
                        [DB::table('sisolm_web.dbo.sw_DJ_SOLICITUD')->where('SOLI_CODIGO', $id)->value('CODI_PERS')]
                    );
                    $correoDestino = trim((string) ($personal->PERS_EMAIL ?? ''));

                    if ($personal && filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
                        $nombre = trim(implode(' ', array_filter([
                            $personal->NOMB_1 ?? null,
                            $personal->NOMB_2 ?? null,
                            $personal->APEL_1 ?? null,
                            $personal->APEL_2 ?? null,
                        ])));
                        $cambiosCorreo = $this->obtenerCambiosSolicitudParaCorreo($id);

                        Mail::mailer('sip')
                            ->to($correoDestino)
                            ->cc(config('mail.dj_aprobada_cc', []))
                            ->send(new \App\Mail\SolicitudDjAprobadaMail([
                                'nombre' => $nombre !== '' ? $nombre : 'Personal',
                                'dni' => trim((string) ($personal->NRO_DOCU_IDEN ?? '')),
                                'fecha' => now()->format('d/m/Y H:i'),
                                'cambios' => $cambiosCorreo,
                            ]));
                        $correoEnviado = true;
                    } else {
                        Log::warning('DJ aprobada: el personal no tiene un correo válido.', ['solicitud' => $id]);
                    }
                } catch (\Throwable $e) {
                    // La aprobación ya quedó confirmada en la BD; el fallo del SMTP
                    // no debe revertirla ni mostrarla como si no se hubiera aplicado.
                    Log::error('No se pudo enviar la confirmación de DJ aprobada.', [
                        'solicitud' => $id,
                        'error' => $e->getMessage(),
                    ]);
                }

                if (!$correoEnviado) {
                    $mensaje .= ' No se pudo enviar el correo de confirmación; revisa el correo registrado y la configuración SMTP.';
                }
            }

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'correo_enviado' => $correoEnviado,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Error resolviendo solicitud DJ del SIP', ['solicitud' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'No se pudo resolver la solicitud.'], 500);
        }
    }

    private function obtenerCambiosSolicitudParaCorreo($id): array
    {
        $etiquetas = [
            'FECH_CADUCA_DNI' => 'Caducidad del DNI',
            'PERS_ESTADO_CIVIL' => 'Estado civil',
            'PERS_TELEFONO' => 'Teléfono personal',
            'PERS_EMAIL' => 'Correo electrónico',
            'PERS_WHATSAPP' => 'WhatsApp',
            'SABE_NADAR' => 'Sabe nadar',
            'PERS_GRUP_SANGRE' => 'Tipo de sangre',
            'PERS_PESO' => 'Peso (kg)',
            'PERS_TALLA' => 'Talla (m)',
            'PERS_SIST_PREV' => 'Sistema previsional',
            'PERS_ESSALUD' => 'EsSalud Vida',
            'PERS_PENSIONISTA' => 'Pensionista',
            'PERS_GRADO_INST' => 'Grado de instrucción',
            'PERS_INSTITUCION' => 'Institución educativa',
            'PERS_CARRERA' => 'Carrera',
            'PERS_ANIO_EGRESO' => 'Año de egreso',
            'dj2026_experiencia_anios' => 'Experiencia laboral anterior (años)',
            'PERS_EMBARGO' => 'Embargos financieros',
            'PERS_BANCO' => 'Cuenta de sueldo',
            'DEPA_CODIGO_NACI' => 'Departamento de nacimiento',
            'PROVI_CODIGO_NACI' => 'Provincia de nacimiento',
            'DIST_NACI' => 'Distrito de nacimiento',
            'PERS_DEPT_ACT' => 'Departamento actual',
            'PERS_PROV_ACT' => 'Provincia actual',
            'PERS_DIST_ACT' => 'Distrito actual',
            'DIRECCION' => 'Dirección actual',
            'PERS_DEPT_DNI' => 'Departamento del DNI',
            'PERS_PROV_DNI' => 'Provincia del DNI',
            'PERS_DIST_DNI' => 'Distrito del DNI',
            'DIRECCION_DNI' => 'Dirección del DNI',
            'PERS_NOMCONTACTO' => 'Contacto de emergencia',
            'PERS_NROEMERGENCIA' => 'Teléfono de emergencia',
            'PERS_PARENT_EME' => 'Parentesco de emergencia',
            'LAB_SMO' => 'Servicio militar',
            'LAB_SUCAMEC' => 'Curso SUCAMEC',
            'LAB_SUCAMEC_OBS' => 'Detalle SUCAMEC',
            'LAB_LIC_ARMA' => 'Licencia de arma',
            'LAB_ARMA_PROPIA' => 'Arma propia',
            'PERS_BREVETE' => 'Número de brevete',
            'CLASE_BREVETE' => 'Clase de brevete',
            'PERS_TIPO_VEHICULO' => 'Categoría de brevete',
            'PERS_VEHICULO_PROPIO' => 'Vehículo propio',
            'PERS_ULTIMA_EMP' => 'Última empresa',
            'PERS_ULTIMO_CARGO' => 'Último cargo',
            'PERS_ULTIMA_DURACION' => 'Duración en el último cargo',
            'dj2026_ocupacion_principal' => 'Ocupación principal',
            'dj2026_descripcion' => 'Ocupaciones adicionales',
            'dj2026_ciudad_naci' => 'Ciudad de nacimiento',
            'dj2026_familiar_empresa' => 'Familiar en la empresa',
            'dj2026_familiar_nombre' => 'Nombre del familiar',
            'dj2026_familiar_parentesco' => 'Parentesco del familiar',
            'DJ_FAMILIARES' => 'Datos familiares',
        ];

        $filas = DB::table('sisolm_web.dbo.sw_DJ_SOLICITUD_DETALLE')
            ->where('SOLI_CODIGO', $id)
            ->orderBy('SOLD_CODIGO')
            ->get(['SOLD_CAMPO', 'SOLD_VALOR_ANTERIOR', 'SOLD_VALOR_PROPUESTO']);

        $cambios = [];
        foreach ($filas as $fila) {
            $campo = (string) $fila->SOLD_CAMPO;
            if (in_array($campo, ['DJ_DATOS_ACTUALES', 'DJ_PROPUESTA_COMPLETA'], true)) continue;

            $anterior = $this->decodificarValorSolicitudCorreo($fila->SOLD_VALOR_ANTERIOR);
            $solicitado = $this->decodificarValorSolicitudCorreo($fila->SOLD_VALOR_PROPUESTO);
            if ($this->normalizarValorSolicitudCorreo($anterior, $campo) === $this->normalizarValorSolicitudCorreo($solicitado, $campo)) continue;

            $cambios[] = [
                'campo' => $etiquetas[$campo] ?? ucwords(str_replace('_', ' ', strtolower($campo))),
                'anterior' => $this->formatearValorSolicitudCorreo($anterior, $campo),
                'solicitado' => $this->formatearValorSolicitudCorreo($solicitado, $campo),
            ];
        }

        return $cambios;
    }

    private function decodificarValorSolicitudCorreo($valor)
    {
        if (!is_string($valor)) return $valor;
        $texto = trim($valor);
        if ($texto === '') return '';

        $decodificado = json_decode($texto, true);
        return json_last_error() === JSON_ERROR_NONE ? $decodificado : $texto;
    }

    private function normalizarValorSolicitudCorreo($valor, string $campo): string
    {
        if ($campo === 'DJ_FAMILIARES') {
            $familiares = [];
            foreach ((array) $valor as $familiar) {
                if (!is_array($familiar)) continue;
                $familiares[] = [
                    mb_strtoupper(trim((string) ($familiar['TIPO_RELA'] ?? $familiar['parentesco'] ?? '')), 'UTF-8'),
                    trim((string) ($familiar['Nombres'] ?? $familiar['nombres'] ?? '')),
                    $this->normalizarFechaSolicitudCorreo($familiar['FECH_NACI'] ?? $familiar['fech_naci'] ?? ''),
                ];
            }
            sort($familiares);
            return json_encode($familiares, JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        if ($campo === 'dj2026_descripcion' && is_array($valor)) {
            $ocupaciones = array_map(static function ($item) {
                return trim((string) $item);
            }, array_values($valor));
            return json_encode($ocupaciones, JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        if (is_array($valor) && array_key_exists('date', $valor)) {
            $valor = $valor['date'];
        }
        if ($valor === null) return '';
        $texto = trim((string) $valor);
        if (stripos($campo, 'FECH') !== false || stripos($campo, 'FECHA') !== false) {
            $fecha = $this->normalizarFechaSolicitudCorreo($texto);
            if ($fecha !== '') return $fecha;
        }
        if (in_array($campo, ['PERS_PESO', 'PERS_TALLA', 'dj2026_experiencia_anios'], true) && is_numeric($texto)) {
            return rtrim(rtrim(number_format((float) $texto, 8, '.', ''), '0'), '.');
        }
        if ($campo === 'PERS_BANCO') {
            $banco = mb_strtoupper($texto, 'UTF-8');
            return [
                'BCP' => '01',
                'BANCO DE CREDITO DEL PERU' => '01',
                'INTERBANK' => '03',
                'BANCO INTERNACIONAL DEL PERU - INTERBANK' => '03',
            ][$banco] ?? $banco;
        }
        return $texto;
    }

    private function normalizarFechaSolicitudCorreo($valor): string
    {
        if (is_array($valor) && isset($valor['date'])) $valor = $valor['date'];
        $texto = trim((string) $valor);
        if ($texto === '') return '';
        foreach (['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s.u'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $texto);
            if ($fecha && $fecha->format($formato) === $texto) return $fecha->format('Y-m-d');
        }
        return $texto;
    }

    private function formatearValorSolicitudCorreo($valor, string $campo): string
    {
        if ($campo === 'DJ_FAMILIARES') {
            $lineas = [];
            foreach ((array) $valor as $familiar) {
                if (!is_array($familiar)) continue;
                $parentesco = trim((string) ($familiar['TIPO_RELA'] ?? $familiar['parentesco'] ?? ''));
                $nombres = trim((string) ($familiar['Nombres'] ?? $familiar['nombres'] ?? ''));
                $fecha = $familiar['FECH_NACI'] ?? $familiar['fech_naci'] ?? '';
                if (is_array($fecha) && isset($fecha['date'])) $fecha = $fecha['date'];
                $fechaTexto = trim((string) $fecha) !== '' ? ' · Nacimiento: ' . $this->formatearFechaSolicitudCorreo($fecha) : '';
                $lineas[] = trim($parentesco . ': ' . $nombres . $fechaTexto);
            }
            return $lineas ? implode("\n", $lineas) : 'Sin registros';
        }

        if ($campo === 'dj2026_descripcion' && is_array($valor)) {
            $ocupaciones = array_values(array_filter(array_map(static function ($item) {
                return trim((string) $item);
            }, $valor), static function ($item) { return $item !== ''; }));
            return $ocupaciones ? implode("\n", array_map(static function ($item, $i) {
                return 'Ocupación ' . ($i + 1) . ': ' . $item;
            }, $ocupaciones, array_keys($ocupaciones))) : 'Sin dato';
        }

        if (is_array($valor) && array_key_exists('date', $valor)) $valor = $valor['date'];
        if (is_array($valor)) return json_encode($valor, JSON_UNESCAPED_UNICODE) ?: 'Sin dato';
        if ($valor === null || trim((string) $valor) === '') return 'Sin dato';
        $texto = trim((string) $valor);

        $catalogo = $this->buscarEtiquetaCatalogoSolicitudCorreo($campo, $texto);
        if ($catalogo !== null) return $catalogo;

        if (stripos($campo, 'FECH') !== false || stripos($campo, 'FECHA') !== false) {
            return $this->formatearFechaSolicitudCorreo($texto);
        }
        if (in_array($campo, ['PERS_PESO', 'PERS_TALLA'], true) && is_numeric($valor)) {
            $numero = number_format((float) $valor, 2, '.', '');
            if ($campo === 'PERS_PESO') $numero = rtrim(rtrim($numero, '0'), '.');
            return $numero . ($campo === 'PERS_PESO' ? ' kg' : ' m');
        }
        if ($campo === 'dj2026_experiencia_anios' && is_numeric($valor)) {
            $numero = rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.');
            return $numero . ((float) $valor === 1.0 ? ' año' : ' años');
        }
        if (in_array($campo, [
            'SABE_NADAR', 'PERS_ESSALUD', 'PERS_PENSIONISTA', 'PERS_EMBARGO', 'LAB_SMO', 'LAB_SUCAMEC',
            'LAB_ARMA_PROPIA', 'PERS_VEHICULO_PROPIO', 'dj2026_familiar_empresa',
        ], true)) {
            $booleanos = ['1' => 'Sí', '0' => 'No', 'S' => 'Sí', 'N' => 'No', 'SI' => 'Sí', 'SÍ' => 'Sí', 'NO' => 'No'];
            return $booleanos[mb_strtoupper($texto, 'UTF-8')] ?? $texto;
        }
        return $texto;
    }

    /** Devuelve nombres en vez de códigos para los valores que vienen de catálogos del SISOL. */
    private function buscarEtiquetaCatalogoSolicitudCorreo(string $campo, string $valor): ?string
    {
        $catalogos = [
            'PERS_ESTADO_CIVIL' => ['si_solm.dbo.ADMI_ESTADO_CIVIL', 'ESCI_CODIGO', 'ESCI_DESCRIPCION'],
            'PERS_SIST_PREV' => ['si_solm.dbo.SISTEMA_PENSIONES', 'CODI_SIST_PENS', 'DESC_SIST_PENS'],
            'PERS_GRADO_INST' => ['si_solm.dbo.SUNAT_NIVEL_EDUCATIVO', 'NIED_CODIGO', 'NIED_ABREVIADO'],
            'PERS_INSTITUCION' => ['si_solm.dbo.SUNAT_IEDUCATIVA', 'IEDU_CODIGO', 'IEDU_DESCRIPCION'],
            'PERS_CARRERA' => ['si_solm.dbo.SUNAT_CARRERAS', 'CARR_CODIGO', 'CARR_DESCRIPCION'],
            'PERS_BANCO' => ['si_solm.dbo.BANCOS', 'CODI_BANC', 'DESC_BANC'],
            'DEPA_CODIGO_NACI' => ['si_solm.dbo.ADMI_DEPARTAMENTO', 'DEPA_CODIGO', 'DEPA_DESCRIPCION'],
            'PROVI_CODIGO_NACI' => ['si_solm.dbo.ADMI_PROVINCIA', 'PROVI_CODIGO', 'PROVI_DESCRIPCION'],
            'DIST_NACI' => ['si_solm.dbo.ADMI_DISTRITO', 'DIST_CODIGO', 'DIST_DESCRIPCION'],
            'PERS_DEPT_ACT' => ['si_solm.dbo.ADMI_DEPARTAMENTO', 'DEPA_CODIGO', 'DEPA_DESCRIPCION'],
            'PERS_DEPT_DNI' => ['si_solm.dbo.ADMI_DEPARTAMENTO', 'DEPA_CODIGO', 'DEPA_DESCRIPCION'],
            'PERS_PROV_ACT' => ['si_solm.dbo.ADMI_PROVINCIA', 'PROVI_CODIGO', 'PROVI_DESCRIPCION'],
            'PERS_PROV_DNI' => ['si_solm.dbo.ADMI_PROVINCIA', 'PROVI_CODIGO', 'PROVI_DESCRIPCION'],
            'PERS_DIST_ACT' => ['si_solm.dbo.ADMI_DISTRITO', 'DIST_CODIGO', 'DIST_DESCRIPCION'],
            'PERS_DIST_DNI' => ['si_solm.dbo.ADMI_DISTRITO', 'DIST_CODIGO', 'DIST_DESCRIPCION'],
        ];

        if (!isset($catalogos[$campo]) || !preg_match('/^[A-Za-z0-9_-]+$/', $valor)) return null;
        [$tabla, $columnaCodigo, $columnaNombre] = $catalogos[$campo];

        try {
            $fila = DB::selectOne(
                "SELECT TOP 1 {$columnaNombre} AS etiqueta FROM {$tabla} WHERE {$columnaCodigo} = ?",
                [$valor]
            );
            $etiqueta = trim((string) ($fila->etiqueta ?? ''));
            if ($etiqueta !== '') {
                if ($campo === 'PERS_SIST_PREV' && trim($valor) === '01') return 'ONP';
                return $etiqueta;
            }
        } catch (\Throwable $e) {
            // Si un catálogo no está disponible, el correo conserva el valor original.
        }

        return null;
    }

    private function formatearFechaSolicitudCorreo($valor): string
    {
        $fecha = $this->normalizarFechaSolicitudCorreo($valor);
        if ($fecha === '') return 'Sin dato';
        $date = \DateTime::createFromFormat('!Y-m-d', substr($fecha, 0, 10));
        return $date ? $date->format('d/m/Y') : $fecha;
    }

    private function aplicarPropuestaSipEnPersonal(string $codiPers, array $propuesta): void
    {
        // La solicitud contiene únicamente diferencias confirmadas al enviarse;
        // por eso se aplican exactamente esos campos, sin recomparar el formulario entero.
        $camposTelefono = ['PERS_TELEFONO', 'PERS_WHATSAPP', 'PERS_NROEMERGENCIA', 'PERS_NOMCONTACTO', 'PERS_PARENT_EME'];
        $telefonosCambiaron = count(array_intersect($camposTelefono, array_keys($propuesta))) > 0;
        $propuestaCompleta = $propuesta;
        $datosAntes = [];

        if ($telefonosCambiaron) {
            $telefonosActuales = $this->getTelefonos($codiPers, 'pendiente');
            $telefonosActuales['PERS_PARENT_EME'] = $telefonosActuales['PERS_EMERC_FAMILIAR'] ?? null;
            foreach ($camposTelefono as $campoTelefono) {
                if (array_key_exists($campoTelefono, $propuesta)) {
                    $telefonosActuales[$campoTelefono] = $propuesta[$campoTelefono];
                }
            }
            $propuestaCompleta = array_merge($telefonosActuales, $propuesta);
            $nombresPersonal = DB::selectOne(
                'SELECT APEL_1, APEL_2, NOMB_1, NOMB_2 FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
                [$codiPers]
            );
            $datosAntes = (array) ($nombresPersonal ?? []);
        }

        $mapa = [
            'FECH_CADUCA_DNI' => ['PERS_FECHCADUCADNI'],
            'PERS_TELEFONO' => ['PERS_TELEFONO'],
            'PERS_EMAIL' => ['PERS_EMAIL'],
            'PERS_WHATSAPP' => ['PERS_WHATSAPP'],
            'SABE_NADAR' => ['PERS_SNADAR'],
            'PERS_GRUP_SANGRE' => ['tipo_sangr'],
            'PERS_PESO' => ['peso_kilo'],
            'PERS_TALLA' => ['tall_metr'],
            'PERS_SIST_PREV' => ['CODI_SIST_PENS'],
            'PERS_ESSALUD' => ['ESSALUD'],
            'PERS_PENSIONISTA' => ['PERS_PENSIONISTA'],
            'PERS_GRADO_INST' => ['PERS_GRADO_INSTRUCCION'],
            'PERS_INSTITUCION' => ['IEDU_CODIGO'],
            'PERS_CARRERA' => ['CARR_CODIGO'],
            'PERS_ANIO_EGRESO' => ['EGRESO_EDUCATIVO'],
            'PERS_EMBARGO' => ['PERS_EMBARGO'],
            'PERS_BANCO' => ['dj2026_banco'],
            'DIRECCION' => ['DIRECCION'],
            'DIRECCION_DNI' => ['PERS_DIREC_DNI'],
            'PERS_NOMCONTACTO' => ['PERS_NOMCONTACTO'],
            'PERS_NROEMERGENCIA' => ['PERS_NROEMERGENCIA'],
            'PERS_PARENT_EME' => ['PERS_EMERC_FAMILIAR'],
            'DEPA_CODIGO_NACI' => ['DEPA_CODIGO_NACI'],
            'PROVI_CODIGO_NACI' => ['PROVI_CODIGO_NACI'],
            'DIST_NACI' => ['DIST_NACI'],
            'PERS_DEPT_ACT' => ['PERS_DEPT_ACT'],
            'PERS_PROV_ACT' => ['PERS_PROV_ACT'],
            'PERS_DIST_ACT' => ['PERS_DIST_ACT'],
            'PERS_DEPT_DNI' => ['PERS_DPTO_DIRDNI'],
            'PERS_PROV_DNI' => ['PERS_PROV_DIRDNI'],
            'PERS_DIST_DNI' => ['PERS_DIST_DIRDNI'],
            'LAB_SUCAMEC' => ['PERS_CONDISCAMEC'],
            'LAB_SUCAMEC_OBS' => ['PERS_NRODISCAMEC'],
            'LAB_LIC_ARMA' => ['PERS_NROLICENCIA'],
            'LAB_ARMA_PROPIA' => ['PERS_CONARMAS'],
            'PERS_BREVETE' => ['PERS_BREVETE'],
            'CLASE_BREVETE' => ['CLASE_BREVETE'],
            'PERS_TIPO_VEHICULO' => ['CATEGORIA_BREVETE'],
            'PERS_VEHICULO_PROPIO' => ['PERS_VEHICULO_PROPIO'],
            'PERS_ULTIMA_EMP' => ['PERS_CTRABANT'],
            'PERS_ULTIMO_CARGO' => ['PERS_CARGOTRABANT'],
            'PERS_ULTIMA_DURACION' => ['PERS_DURACIONANT'],
            'dj2026_ocupacion_principal' => ['dj2026_ocupacion_principal'],
            'dj2026_experiencia_anios' => ['dj2026_experiencia_anios'],
            'dj2026_familiar_empresa' => ['dj2026_familiar_empresa'],
            'dj2026_familiar_nombre' => ['dj2026_familiar_nombre'],
            'dj2026_familiar_parentesco' => ['dj2026_familiar_parentesco'],
        ];

        $updates = [];
        foreach ($mapa as $campo => $columnas) {
            if (!array_key_exists($campo, $propuesta) || is_array($propuesta[$campo])) continue;
            $valor = $propuesta[$campo] === '' ? null : $propuesta[$campo];
            if (str_starts_with($campo, 'FECH_') && $valor !== null) {
                $valor = $this->fechaSipParaSql($valor);
            }
            foreach ($columnas as $columna) $updates[$columna] = $valor;
        }

        // SIP envía el código ESCI_CODIGO (por ejemplo, 2007000001), pero
        // ESTA_CIVI es el campo legado CHAR(1) y debe recibir S/C/D/V/O.
        if (array_key_exists('PERS_ESTADO_CIVIL', $propuesta)) {
            $estadoCivilIngresado = trim((string) $propuesta['PERS_ESTADO_CIVIL']);
            $codigoLegadoPorDescripcion = [
                'SOLTERO' => 'S', 'CASADO' => 'C', 'DIVORCIADO' => 'D',
                'VIUDO' => 'V', 'CONVIVIENTE' => 'O',
            ];
            $descripcionBuscada = $codigoLegadoPorDescripcion[strtoupper($estadoCivilIngresado)] ?? null;
            $estadoCivil = DB::table('si_solm.dbo.ADMI_ESTADO_CIVIL')
                ->where('ESCI_CODIGO', $estadoCivilIngresado)
                ->first(['ESCI_CODIGO', 'ESCI_DESCRIPCION']);

            if (!$estadoCivil && $descripcionBuscada === null) {
                $descripcionBuscada = strtoupper($estadoCivilIngresado);
            }
            if (!$estadoCivil && $descripcionBuscada !== null) {
                $estadoCivil = DB::table('si_solm.dbo.ADMI_ESTADO_CIVIL')
                    ->whereRaw('UPPER(LTRIM(RTRIM(ESCI_DESCRIPCION))) = ?', [$descripcionBuscada])
                    ->first(['ESCI_CODIGO', 'ESCI_DESCRIPCION']);
            }
            if (!$estadoCivil) {
                throw new \RuntimeException('No se encontró el estado civil seleccionado en el catálogo del SISOL.');
            }

            $descripcionEstado = strtoupper(trim((string) $estadoCivil->ESCI_DESCRIPCION));
            if (!isset($codigoLegadoPorDescripcion[$descripcionEstado])) {
                throw new \RuntimeException('El estado civil seleccionado no tiene un código compatible con el registro del personal.');
            }
            $updates['ESCI_CODIGO'] = $estadoCivil->ESCI_CODIGO;
            $updates['ESTA_CIVI'] = $codigoLegadoPorDescripcion[$descripcionEstado];
        }

        if (array_key_exists('LAB_SMO', $propuesta)) {
            $smo = trim((string) $propuesta['LAB_SMO']);
            if ($smo !== '') {
                $updates['PERS_CONSMO'] = (strtoupper($smo) !== 'NO') ? 'SI' : 'NO';
                $updates['PERS_LUGARSMO'] = ($updates['PERS_CONSMO'] === 'SI') ? $smo : 'NO';
            }
        }

        $ocupaciones = $propuesta['dj2026_descripcion'] ?? null;
        if (array_key_exists('dj2026_descripcion', $propuesta) && is_array($ocupaciones)) {
            $ocupaciones = array_values(array_filter($ocupaciones, fn ($v) => trim((string) $v) !== ''));
            $updates['dj2026_cantprofesion'] = count($ocupaciones);
            $updates['dj2026_laboral_1'] = isset($ocupaciones[0]) ? mb_strtoupper($ocupaciones[0], 'UTF-8') : null;
            $updates['dj2026_laboral_2'] = isset($ocupaciones[1]) ? mb_strtoupper($ocupaciones[1], 'UTF-8') : null;
        }
        if (array_key_exists('dj2026_ciudad_naci', $propuesta)) $updates['dj2026_ciudad_naci'] = $propuesta['dj2026_ciudad_naci'] ?: null;

        if (!$updates && !$telefonosCambiaron && !isset($propuesta['FAM_NOMBRES'])) return;
        if ($telefonosCambiaron) {
            // La migración telefónica necesita el estado final completo aunque
            // solo uno de los teléfonos haya cambiado.
            $propuestaTelefonos = $propuestaCompleta;
        }
        $updates['USUA_CODIGO_MOD'] = (string) (session('usuario') ?? 'SISOL');
        $sets = [];
        $params = [];
        foreach ($updates as $columna => $valor) {
            $sets[] = '[' . $columna . '] = ?';
            $params[] = $valor;
        }
        $sets[] = '[USUA_FECHA_MOD] = GETDATE()';
        $params[] = $codiPers;
        DB::update('UPDATE si_solm.dbo.PERSONAL SET ' . implode(', ', $sets) . ' WHERE CODI_PERS = ?', $params);

        // Los datos relacionados se aplican con los mismos métodos que Actualizar DJ.
        if (isset($propuesta['FAM_NOMBRES']) && is_array($propuesta['FAM_NOMBRES'])) {
            $this->saveFamiliaresTemp($codiPers, $propuesta);
            $this->migrarFamiliares($codiPers);
        }
        if ($telefonosCambiaron) {
            $datosTelefono = $propuestaTelefonos;
            $telefonos = [
                'celular' => $datosTelefono['PERS_TELEFONO'] ?? '',
                'whatsapp' => $datosTelefono['PERS_WHATSAPP'] ?? '',
                'celular_emergencia' => $datosTelefono['PERS_NROEMERGENCIA'] ?? '',
                'contacto_emergencia' => $datosTelefono['PERS_NOMCONTACTO'] ?? '',
                'parentesco_emergencia' => $datosTelefono['PERS_PARENT_EME'] ?? '',
                'apellido_paterno' => $datosAntes['APEL_1'] ?? '',
                'apellido_materno' => $datosAntes['APEL_2'] ?? '',
                'nombre1' => $datosAntes['NOMB_1'] ?? '',
                'nombre2' => $datosAntes['NOMB_2'] ?? '',
            ];
            $this->saveTelefonosTemp($codiPers, $telefonos);
            $this->migrarTelefonos($codiPers);
        }
    }

    private function solicitudSipCampoCambio(string $campo, array $aliases, array $datosAntes, array $propuesta): bool
    {
        if (!array_key_exists($campo, $propuesta)) return false;
        $valorAnterior = null;
        $seEncontroAlias = false;
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $datosAntes)) {
                if (!$seEncontroAlias) {
                    $valorAnterior = $datosAntes[$alias];
                    $seEncontroAlias = true;
                }
                $valorAlias = $datosAntes[$alias];
                if (is_array($valorAlias) && array_key_exists('date', $valorAlias)) $valorAlias = $valorAlias['date'];
                if ($valorAlias instanceof \DateTimeInterface) $valorAlias = $valorAlias->format('Y-m-d');
                if ($valorAlias !== null && trim((string) $valorAlias) !== '') {
                    return !$this->valoresSolicitudSipEquivalentes($valorAlias, $propuesta[$campo], $campo);
                }
            }
        }
        return $seEncontroAlias
            ? !$this->valoresSolicitudSipEquivalentes($valorAnterior, $propuesta[$campo], $campo)
            : false;
    }

    private function valoresSolicitudSipEquivalentes($antes, $propuesto, string $campo): bool
    {
        if (is_array($antes) && array_key_exists('date', $antes)) $antes = $antes['date'];
        if (is_array($propuesto) && array_key_exists('date', $propuesto)) $propuesto = $propuesto['date'];
        if ($antes instanceof \DateTimeInterface) $antes = $antes->format('Y-m-d');
        if ($propuesto instanceof \DateTimeInterface) $propuesto = $propuesto->format('Y-m-d');
        if ($antes === null || trim((string) $antes) === '') $antes = '';
        if ($propuesto === null || trim((string) $propuesto) === '') $propuesto = '';
        $a = trim((string) $antes);
        $b = trim((string) $propuesto);

        if ($a === '' || $b === '') return $a === $b;
        if (stripos($campo, 'FECH') !== false || stripos($campo, 'FECHA') !== false) {
            $normalizar = function (string $valor): string {
                foreach (['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s.u', 'Y-m-d H:i:s'] as $formato) {
                    $fecha = \DateTime::createFromFormat($formato, $valor);
                    if ($fecha) return $fecha->format('Y-m-d');
                }
                return $valor;
            };
            return $normalizar($a) === $normalizar($b);
        }
        if (in_array($campo, ['PERS_PESO', 'PERS_TALLA', 'PERS_ANIO_EGRESO', 'dj2026_experiencia_anios'], true)
            && is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }
        if (in_array($campo, [
            'PERS_ESTADO_CIVIL', 'PERS_SIST_PREV', 'PERS_GRUP_SANGRE', 'PERS_BANCO',
            'PERS_ESSALUD', 'PERS_PENSIONISTA', 'PERS_EMBARGO', 'LAB_SUCAMEC',
            'LAB_ARMA_PROPIA', 'LAB_SMO', 'PERS_VEHICULO_PROPIO', 'CLASE_BREVETE',
            'PERS_TIPO_VEHICULO', 'PERS_PARENT_EME', 'dj2026_familiar_empresa',
            'dj2026_familiar_parentesco', 'dj2026_descripcion',
        ], true)) {
            $a = mb_strtoupper($a, 'UTF-8');
            $b = mb_strtoupper($b, 'UTF-8');
        }
        if ($campo === 'PERS_BANCO') {
            $bancos = [
                'BCP' => '01', 'BANCO DE CREDITO DEL PERU' => '01',
                'INTERBANK' => '03', 'BANCO INTERNACIONAL DEL PERU - INTERBANK' => '03',
            ];
            $a = $bancos[$a] ?? $a;
            $b = $bancos[$b] ?? $b;
        }
        return $a === $b;
    }

    private function normalizarFamiliaresSolicitudSip(array $familiares): array
    {
        $normalizados = [];
        foreach ($familiares as $familiar) {
            if (!is_array($familiar)) continue;
            $parentesco = mb_strtoupper(trim((string) ($familiar['TIPO_RELA'] ?? $familiar['parentesco'] ?? '')), 'UTF-8');
            $nombre = mb_strtoupper(trim((string) ($familiar['Nombres'] ?? $familiar['nombres'] ?? '')), 'UTF-8');
            if ($nombre === '') continue;
            $fecha = $parentesco === 'HIJO MENOR DE EDAD'
                ? $this->normalizarFechaSolicitudSip($familiar['FECH_NACI'] ?? $familiar['fech_naci'] ?? '')
                : '';
            $normalizados[] = [$parentesco, $nombre, $fecha];
        }
        sort($normalizados);
        return $normalizados;
    }

    private function normalizarFechaSolicitudSip($valor): string
    {
        if (is_array($valor) && isset($valor['date'])) $valor = $valor['date'];
        if ($valor instanceof \DateTimeInterface) return $valor->format('Y-m-d');
        $texto = trim((string) $valor);
        if ($texto === '') return '';
        foreach (['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s.u', 'Y-m-d H:i:s'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $texto);
            if ($fecha) return $fecha->format('Y-m-d');
        }
        return $texto;
    }

    private function fechaSipParaSql($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') return null;
        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $fecha = \DateTime::createFromFormat($formato, $valor);
            // SQL Server puede interpretar YYYY-MM-DD según DATEFORMAT de la
            // conexión cuando el parámetro llega como nvarchar. YYYYMMDD es
            // inequívoco para convertirlo a datetime en cualquier idioma.
            if ($fecha && $fecha->format($formato) === $valor) return $fecha->format('Ymd');
        }
        throw new \RuntimeException('Una fecha propuesta no tiene un formato válido.');
    }

    public function reportePersonalSinMigracion(Request $request)
    {
        try {
            $usuario = session('usuario') ?? '0';
            $codEmpresa = $request->get('codEmpresa', '01');

            // 1. Ejecutamos Campaña Anual (Sintaxis 100% segura para SQL Server)
            $queryAnual = DB::select(
                "EXEC sisolm_web.dbo.SW_LISTAR_PERSONAL_DJ_MIGRACION_ETAPA2_VIGENTES_DNI ?, ?, '00', '00', NULL, 'anual'",
                [$usuario, $codEmpresa]
            );

            // Inyectamos la columna faltante a la fuerza
            $dataAnual = array_map(function($item) {
                $item->tipo_actualizacion = 'anual';
                return $item;
            }, $queryAnual);

            // Demanda se alimenta exclusivamente de solicitudes hechas desde el SIP.
            // El SP de migración incluye registros históricos de sw_MIGRA_PERSONAL,
            // que no representan solicitudes y no deben aparecer en esta pestaña.
            $solicitudesSip = DB::select(
                "SELECT r.SOLI_CODIGO AS solicitud_id, r.CODI_PERS AS codPersonal,
                        p.CODI_PERS AS id, p.APEL_1 AS apellido1, p.APEL_2 AS apellido2,
                        p.NOMB_1 AS nombres, p.NOMB_2 AS NOMB_2, p.NRO_DOCU_IDEN AS dni,
                        p.SUCU_CODIGO AS codSucursal,
                        COALESCE(s.SUCU_ABREVIATURA, p.SUCU_CODIGO) AS sucursal,
                        COALESCE(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) AS tipoPer,
                        COALESCE(tp.TIPE_DESCRIPCION, p.PERS_TIPOTRAB) AS TIPO_PER,
                        p.CODI_CARG AS codCargo, c.DESC_CARGO AS cargo,
                        CASE WHEN r.SOLI_ESTADO = 'APROBADA' THEN 'SI' ELSE 'NO' END AS migrado,
                        r.SOLI_ESTADO AS estado_solicitud,
                        'demanda' AS tipo_actualizacion,
                        COALESCE(r.SOLI_FECHA_APLICA, r.SOLI_FECHA_REG) AS cambio,
                        CAST(1 AS bit) AS es_solicitud_nueva
                 FROM sisolm_web.dbo.sw_DJ_SOLICITUD r
                 INNER JOIN si_solm.dbo.PERSONAL p ON LTRIM(RTRIM(p.CODI_PERS)) COLLATE DATABASE_DEFAULT = LTRIM(RTRIM(r.CODI_PERS)) COLLATE DATABASE_DEFAULT
                 LEFT JOIN si_solm.dbo.SISO_SUCURSAL s ON s.SUCU_CODIGO COLLATE DATABASE_DEFAULT = p.SUCU_CODIGO COLLATE DATABASE_DEFAULT
                 LEFT JOIN si_solm.dbo.ADMI_TIPO_PERSONAL tp ON tp.TIPE_CODIGO COLLATE DATABASE_DEFAULT = p.PERS_TIPOTRAB COLLATE DATABASE_DEFAULT
                 LEFT JOIN si_solm.dbo.CARGOS c ON c.CODI_CARG COLLATE DATABASE_DEFAULT = p.CODI_CARG COLLATE DATABASE_DEFAULT
                 WHERE r.SOLI_ESTADO IN ('PENDIENTE', 'APROBADA')
                   AND r.EMPR_CODIGO <> '05'"
            );

            $data = array_merge($dataAnual, $solicitudesSip);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en reportePersonalSinMigracion: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // public function reportePersonalSinMigracion(Request $request)
    // {
    //     try {
    //         $usuario = session('usuario') ?? '0';
    //         $codEmpresa = $request->get('codEmpresa', '01');

    //         // 🔥 Mandamos la estructura completa con el 'TODOS' al final 🔥
    //         $data = DB::select(
    //             "EXEC sisolm_web.dbo.prueba ?, ?, '00', '00', NULL, 'TODOS'",
    //             [$usuario, $codEmpresa]
    //         );

    //         return response()->json([
    //             'success' => true,
    //             'data' => $data,
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Error en reportePersonalSinMigracion: ' . $e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function reportePersonalSinMigracionV2(Request $request)
    {
        try {
            $codSucursal = $request->get('codSucursal', '00');
            $codTipoPer = $request->get('codTipoPer', '00');
            $tipo = $request->get('tipo', null);

            $data = DB::select(
                'EXEC sisolm_web.dbo.SW_REPORTE_LISTAR_PERSONAL_SIN_MIGRACION ?, ?, ?',
                [$codSucursal, $codTipoPer, $tipo]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en reportePersonalSinMigracionV2: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reportePersonalEtapa2PDF(Request $request)
    {
        try {
            $codSucursal = $request->get('codSucursal', '00');
            $codTipoPer = $request->get('codTipoPer', '00');

            // Ejecutando el nuevo SP de la 2da Etapa
            $data = DB::select(
                'EXEC sisolm_web.dbo.SW_REPORTE_LISTAR_PERSONAL_SIN_MIGRACION_2_ETAPA ?, ?',
                [$codSucursal, $codTipoPer]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en reportePersonalEtapa2PDF: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


    public function updateCheckPdf(Request $request)
    {
        try {
            $codigos = $request->input('codigos');
            $codPeriodo = $request->input('codPeriodo', '2026');
            $usuario = session('usuario') ?? 'SOPORTE';

            if (empty($codigos) || !is_array($codigos)) {
                return response()->json(['success' => false, 'message' => 'Códigos requeridos'], 400);
            }

            $now = now();

            foreach ($codigos as $codPersonal) {
                $codPersonal = str_pad(trim($codPersonal), 5, '0', STR_PAD_LEFT);

                $existing = DB::table('sisolm_web.dbo.sw_dj_generada_x_periodo')
                    ->where('codPeriodo', $codPeriodo)
                    ->where('codPersonal', $codPersonal)
                    ->where('habilitado', 1)
                    ->first();

                if ($existing) {
                    DB::table('sisolm_web.dbo.sw_dj_generada_x_periodo')
                        ->where('codigo', $existing->codigo)
                        ->update([
                            'generado' => 1,
                            'modificadoPor' => $usuario,
                            'fechaModificacion' => $now,
                        ]);
                } else {
                    DB::table('sisolm_web.dbo.sw_dj_generada_x_periodo')
                        ->insert([
                            'codPeriodo' => $codPeriodo,
                            'codPersonal' => $codPersonal,
                            'generado' => 1,
                            'creadoPor' => $usuario,
                            'fechaCreacion' => $now,
                            'habilitado' => 1,
                        ]);
                }
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('Error en updateCheckPdf: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function resetCheckPdf(Request $request)
    {
        try {
            $codigos = $request->input('codigos');
            $codPeriodo = $request->input('codPeriodo', '2026');
            $usuario = session('usuario') ?? 'SOPORTE';
            $now = now();

            $query = DB::table('sisolm_web.dbo.sw_dj_generada_x_periodo')
                ->where('codPeriodo', $codPeriodo)
                ->where('habilitado', 1);

            if (!empty($codigos) && is_array($codigos)) {
                $codigos = array_map(fn($c) => str_pad(trim($c), 5, '0', STR_PAD_LEFT), $codigos);
                $query->whereIn('codPersonal', $codigos);
            }

            $updated = $query->update([
                'habilitado' => 0,
                'modificadoPor' => $usuario,
                'fechaModificacion' => $now,
            ]);

            return response()->json(['success' => true, 'updated' => $updated]);

        } catch (\Exception $e) {
            Log::error('Error en resetCheckPdf: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    private function saveTelefonosTemp($codiPers, $data)
    {
        $telPersonal   = isset($data['celular'])           ? trim($data['celular'])           : '';
        $telWsp        = isset($data['whatsapp'])          ? trim($data['whatsapp'])          : '';
        $telEmergencia = isset($data['celular_emergencia']) ? trim($data['celular_emergencia']) : '';

        // $observNombre = trim(
        //     (isset($data['APEL_1']) ? $data['APEL_1'].' ' : '').
        //     (isset($data['APEL_2']) ? $data['APEL_2'].' ' : '').
        //     (isset($data['NOMB_1']) ? $data['NOMB_1'].' ' : '').
        //     (isset($data['NOMB_2']) ? $data['NOMB_2']      : '')
        // );
        $observNombre = trim(
            (isset($data['apellido_paterno']) ? $data['apellido_paterno'].' ' : '').
            (isset($data['apellido_materno']) ? $data['apellido_materno'].' ' : '').
            (isset($data['nombre1'])          ? $data['nombre1'].' '          : '').
            (isset($data['nombre2'])          ? $data['nombre2']              : '')
        );

        // 1. Borrar registros previos
        DB::delete(
            'DELETE FROM sisolm_web.dbo.sw_MIGRA_TELEFONO WHERE CODI_PERS = ?',
            [$codiPers]
        );

        $telefonos = [];

        // 2. Lógica personal vs whatsapp
        if ($telPersonal === $telWsp) {
            // Son iguales → un solo registro con NRO_WSP = 1
            if (!empty($telPersonal) && strlen($telPersonal) <= 12) {
                $telefonos[] = [
                    'NRO_TELE'         => $telPersonal,
                    'TIPO_TELE'        => 'MOVIL',
                    'TELE_RESERVADO'   => '0',
                    'TELE_EMERGENCIA'  => '0',
                    'NRO_WSP'         => 1,
                    'TELE_CONTACTO'    => null,
                    'VINCULO_FAMILIAR' => null,
                    'OBSERVACION'      => $observNombre,
                ];
            }
        } else {
            // Son diferentes → dos registros
            if (!empty($telPersonal) && strlen($telPersonal) <= 12) {
                $telefonos[] = [
                    'NRO_TELE'         => $telPersonal,
                    'TIPO_TELE'        => 'MOVIL',
                    'TELE_RESERVADO'   => '0',
                    'TELE_EMERGENCIA'  => '0',
                    'NRO_WSP'         => 0,
                    'TELE_CONTACTO'    => null,
                    'VINCULO_FAMILIAR' => null,
                    'OBSERVACION'      => $observNombre,
                ];
            }
            if (!empty($telWsp) && strlen($telWsp) <= 12) {
                $telefonos[] = [
                    'NRO_TELE'         => $telWsp,
                    'TIPO_TELE'        => 'MOVIL',
                    'TELE_RESERVADO'   => '0',
                    'TELE_EMERGENCIA'  => '0',
                    'NRO_WSP'         => 1,
                    'TELE_CONTACTO'    => null,
                    'VINCULO_FAMILIAR' => null,
                    'OBSERVACION'      => $observNombre,
                ];
            }
        }

        // Emergencia
        if (!empty($telEmergencia) && strlen($telEmergencia) <= 12) {
            $telefonos[] = [
                'NRO_TELE'         => $telEmergencia,
                'TIPO_TELE'        => 'MOVIL',          // predeterminado
                'TELE_RESERVADO'   => null,             // null predeterminado
                'TELE_EMERGENCIA'  => null,             // null predeterminado
                'NRO_WSP'          => null,             // null predeterminado (sin checkbox)
                'TELE_CONTACTO'    => isset($data['contacto_emergencia']) ? trim($data['contacto_emergencia']) : null,
                'VINCULO_FAMILIAR' => isset($data['parentesco_emergencia']) ? trim($data['parentesco_emergencia']) : null,
                'OBSERVACION'      => null,             // null predeterminado
            ];
        }

        // 3. Insertar
        foreach ($telefonos as $tel) {
            DB::insert(
                'INSERT INTO sisolm_web.dbo.sw_MIGRA_TELEFONO
                (CODI_PERS, NRO_TELE, TIPO_TELE, OBSERVACION,
                TELE_VIGENCIA, TELE_RESERVADO, TELE_EMERGENCIA,
                NRO_WSP, TELE_CONTACTO, VINCULO_FAMILIAR)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $codiPers,
                    substr($tel['NRO_TELE'], 0, 12),
                    $tel['TIPO_TELE'],
                    $tel['OBSERVACION'],
                    'SI',                                // TELE_VIGENCIA predeterminado
                    $tel['TELE_RESERVADO'],               // emergencia → null, personal/whatsapp → '0'
                    $tel['TELE_EMERGENCIA'],
                    $tel['NRO_WSP'],
                    $tel['TELE_CONTACTO'],
                    $tel['VINCULO_FAMILIAR'],
                ]
            );
        }

        Log::info('saveTelefonosTemp: teléfonos guardados en sw_MIGRA_TELEFONO', [
            'CODI_PERS' => $codiPers,
            'total'     => count($telefonos),
        ]);
    }

    private function migrarTelefonos($codiPers)
    {
        // La tabla temporal representa el estado final deseado. En TELEFONO
        // reutilizamos las filas existentes por función (personal, WhatsApp,
        // emergencia) para que cada edición no borre y vuelva a crear todo.
        $propuestos = DB::select(
            'SELECT CODI_PERS, NRO_TELE, TIPO_TELE, OBSERVACION,
                    TELE_VIGENCIA, TELE_RESERVADO, TELE_EMERGENCIA,
                    NRO_WSP, TELE_CONTACTO, VINCULO_FAMILIAR
             FROM (
                SELECT *, ROW_NUMBER() OVER (
                    PARTITION BY CODI_PERS, LTRIM(RTRIM(NRO_TELE))
                    ORDER BY CASE WHEN TELE_EMERGENCIA IS NULL OR TELE_EMERGENCIA = \'1\' THEN 0 ELSE 1 END
                ) AS numero_fila
                FROM sisolm_web.dbo.sw_MIGRA_TELEFONO
                WHERE CODI_PERS = ?
             ) AS t WHERE numero_fila = 1',
            [$codiPers]
        );

        $existentes = DB::select(
            'SELECT CODI_PERS, NRO_TELE, TIPO_TELE, OBSERVACION,
                    TELE_VIGENCIA, TELE_RESERVADO, TELE_EMERGENCIA,
                    NRO_WSP, TELE_CONTACTO, VINCULO_FAMILIAR
             FROM si_solm.dbo.TELEFONO WHERE CODI_PERS = ?',
            [$codiPers]
        );

        $usados = [];
        $activos = [];
        $numerosPropuestos = [];
        foreach ($propuestos as $telefonoPropuesto) {
            $numero = trim((string) $telefonoPropuesto->NRO_TELE);
            if ($numero !== '') $numerosPropuestos[$numero] = true;
        }
        foreach ($propuestos as $propuesto) {
            $emergencia = $propuesto->TELE_EMERGENCIA === null || $propuesto->TELE_EMERGENCIA == '1';
            $rol = $emergencia ? 'emergencia' : ((int) $propuesto->NRO_WSP === 1 ? 'whatsapp' : 'personal');
            $numeroPropuesto = trim((string) $propuesto->NRO_TELE);

            // La clave primaria de TELEFONO es (CODI_PERS, NRO_TELE). Si el
            // número destino ya existe con otro rol, se reutiliza esa fila para
            // evitar que una actualización genere una clave duplicada.
            $fila = null;
            foreach ($existentes as $existente) {
                $claveExistente = trim((string) $existente->NRO_TELE);
                if ($claveExistente === $numeroPropuesto && !isset($usados[$claveExistente])) {
                    $fila = $existente;
                    break;
                }
            }

            // Primero reutilizar la fila del mismo rol. Si personal y WhatsApp
            // comparten número, cualquiera de esas dos filas puede representar ambos.
            $candidatos = array_values(array_filter($existentes, function ($filaExistente) use ($rol, $numeroPropuesto) {
                $esEmergencia = $filaExistente->TELE_EMERGENCIA === null || $filaExistente->TELE_EMERGENCIA == '1';
                if ($rol === 'emergencia') return $esEmergencia;
                if ($esEmergencia) return false;
                if ($rol === 'whatsapp') return (int) $filaExistente->NRO_WSP === 1
                    || trim((string) $filaExistente->NRO_TELE) === $numeroPropuesto;
                return (int) $filaExistente->NRO_WSP === 0;
            }));

            if (!$fila) {
                foreach ($candidatos as $candidato) {
                    $clave = trim((string) $candidato->NRO_TELE);
                    // Reserva los destinos de esta misma operación para evitar
                    // cruces entre roles al actualizar varias filas.
                    if (isset($numerosPropuestos[$clave])) continue;
                    if (!isset($usados[$clave])) { $fila = $candidato; break; }
                }
            }

            $valores = [
                'NRO_TELE' => trim((string) $propuesto->NRO_TELE),
                'TIPO_TELE' => $propuesto->TIPO_TELE,
                'OBSERVACION' => $propuesto->OBSERVACION,
                'TELE_VIGENCIA' => 'SI',
                'TELE_RESERVADO' => trim((string) ($propuesto->TELE_RESERVADO ?? '')) ?: '0',
                'TELE_EMERGENCIA' => $propuesto->TELE_EMERGENCIA,
                'NRO_WSP' => $propuesto->NRO_WSP,
                'TELE_CONTACTO' => $propuesto->TELE_CONTACTO,
                'VINCULO_FAMILIAR' => $propuesto->VINCULO_FAMILIAR,
            ];

            if ($fila) {
                DB::table('si_solm.dbo.TELEFONO')
                    ->where('CODI_PERS', $codiPers)
                    ->where('NRO_TELE', $fila->NRO_TELE)
                    ->update($valores);
                $usados[trim((string) $fila->NRO_TELE)] = true;
                $usados[$numeroPropuesto] = true;
            } else {
                // Solo se inserta cuando realmente se agrega un teléfono nuevo
                // y no existe una fila previa que se pueda reutilizar.
                DB::table('si_solm.dbo.TELEFONO')->insert(['CODI_PERS' => $codiPers] + $valores);
            }
            $activos[] = $numeroPropuesto;
        }

        // Si personal y WhatsApp se unificaron en un número, desactivar la fila
        // sobrante en vez de borrarla; así no queda como teléfono vigente.
        foreach ($existentes as $fila) {
            $clave = trim((string) $fila->NRO_TELE);
            if (!isset($usados[$clave]) && !in_array($clave, $activos, true)) {
                DB::table('si_solm.dbo.TELEFONO')
                    ->where('CODI_PERS', $codiPers)
                    ->where('NRO_TELE', $fila->NRO_TELE)
                    ->update(['TELE_VIGENCIA' => 'NO']);
            }
        }

        Log::info('migrarTelefonos: teléfonos migrados a si_solm.dbo.TELEFONO', [
            'CODI_PERS' => $codiPers,
        ]);
    }


    /**
     * Subir DNI de HIJOS (anverso y/o reverso) → carpetas DNI1_HIJOS / DNI2_HIJOS
     * Nombres: {CODI_PERS}_H{N}.jpg — H1/H2 = hijo 1 (anverso/reverso), H3/H4 = hijo 2, ...
     * Recibe pares archivos[] + metas[] con formato "DNI1_HIJOS|00085_H1.jpg"
     */
    public function uploadDniHijos(Request $request)
    {
        $request->validate([
            'codi_pers'  => 'required|string',
            'archivos'   => 'required|array|min:1',
            'archivos.*' => 'file|mimes:jpg,jpeg|max:10240',
            'metas'      => 'required|array|min:1',
            'metas.*'    => 'string',
        ], [
            'archivos.*.mimes' => 'Las imágenes del DNI deben ser JPG.',
            'archivos.*.max'   => 'Cada imagen del DNI no debe superar los 10 MB.',
        ]);

        try {
            $codiPers = trim($request->input('codi_pers'));
            $archivos = $request->file('archivos', []);
            $metas    = $request->input('metas', []);

            if (count($archivos) !== count($metas)) {
                return response()->json(['success' => false, 'message' => 'La cantidad de archivos y destinos no coincide.'], 422);
            }

            $fallos = [];
            foreach ($archivos as $i => $archivo) {
                $meta = trim((string) ($metas[$i] ?? ''));
                // Formato permitido: DNI1_HIJOS|CODI_H#.jpg (DNI1_HIJOS = anverso, DNI2_HIJOS = reverso)
                if (!preg_match('/^(DNI1_HIJOS|DNI2_HIJOS)\|([A-Za-z0-9_\-]+\.jpg)$/i', $meta, $m)) {
                    $fallos[] = $meta !== '' ? $meta : ('archivo #' . ($i + 1));
                    continue;
                }
                $ruta     = strtoupper($m[1]);
                $nameFile = $m[2];

                $response = Http::withToken('457862h45hj7u5126h58d2s51s2s')
                    ->attach('archivo', file_get_contents($archivo->getRealPath()), $nameFile)
                    ->post('http://190.116.178.163/apps/api/file-control/charge_file.php', [
                        'nameFile' => $nameFile,
                        'ruta'     => $ruta,
                    ]);

                $proxyData = $response->json();
                if ($response->failed() || (isset($proxyData['success']) && $proxyData['success'] === false)) {
                    $fallos[] = $nameFile;
                    Log::error('uploadDniHijos: fallo en proxy', [
                        'codi_pers' => $codiPers,
                        'destino'   => $meta,
                        'status'    => $response->status(),
                        'body'      => $response->body(),
                    ]);
                }
            }

            if ($fallos) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron guardar algunos DNI: ' . implode(', ', $fallos),
                ], 500);
            }

            Log::info('uploadDniHijos: DNI de hijos guardado', [
                'codi_pers' => $codiPers,
                'total'     => count($archivos),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DNI de los hijos guardado correctamente.',
            ]);

        } catch (\Exception $e) {
            Log::error('uploadDniHijos error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno al subir el DNI de hijos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta qué DNI de hijos YA existen en el servidor (para no volverlos a pedir).
     * Recibe nombres[] tipo "00085_H1.jpg"; el lado se deduce: H impar = DNI1_HIJOS (anverso),
     * H par = DNI2_HIJOS (reverso). Responde { nombre: true/false }.
     */
    public function getDniHijos(Request $request)
    {
        try {
            $nombres = $request->get('nombres', []);
            if (!is_array($nombres)) $nombres = [];

            $resultado = [];
            foreach ($nombres as $nombre) {
                $nombre = trim((string) $nombre);
                if ($nombre === '' || !preg_match('/_H(\d+)\.jpg$/i', $nombre, $m)) continue;
                $numero = (int) $m[1];
                $ruta   = ($numero % 2 === 1) ? 'DNI1_HIJOS' : 'DNI2_HIJOS';
                $url    = "http://190.116.178.163/Biblioteca_Grafica/{$ruta}/{$nombre}";

                $existe = false;
                try {
                    $existe = Http::timeout(5)->head($url)->successful();
                } catch (\Exception $e) {
                    $existe = false;
                }
                $resultado[$nombre] = $existe;
            }

            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (\Exception $e) {
            Log::error('getDniHijos error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function parentescoHijoConDiscapacidadPermanente($parentesco): bool
    {
        $parentesco = mb_strtoupper(trim((string) $parentesco), 'UTF-8');
        return str_starts_with($parentesco, 'HIJO MAYOR DE EDAD')
            && (str_contains($parentesco, 'INCAPACITADO') || str_contains($parentesco, 'DISCAPACIDAD'))
            && str_contains($parentesco, 'PERMANENT');
    }

    private function edadDesdeFechaNacimiento($fecha): ?int
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') return null;
        try {
            $nacimiento = \Carbon\Carbon::parse($fecha);
            if ($nacimiento->isFuture()) return null;
            return $nacimiento->age;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Valida anverso y reverso de DNI para hijos con asignación familiar.
     * En DJ existente admite los archivos que ya están guardados en el servidor.
     */
    private function validarDniHijosRequeridos(Request $request, array $data, ?string $codiPers, bool $esExistente): ?array
    {
        $parentescos = is_array($data['FAM_PARENTESCO'] ?? null) ? $data['FAM_PARENTESCO'] : [];
        $fechas = is_array($data['FAM_FECHA_NACI'] ?? null) ? $data['FAM_FECHA_NACI'] : [];
        $numeroHijo = 0;

        foreach ($parentescos as $i => $parentesco) {
            $parentesco = mb_strtoupper(trim((string) $parentesco), 'UTF-8');
            if (!str_starts_with($parentesco, 'HIJO')) continue;
            $numeroHijo++;

            $edad = $this->edadDesdeFechaNacimiento($fechas[$i] ?? '');
            $esMenorSeleccionado = $parentesco === 'HIJO MENOR DE EDAD';
            $forzarDniNuevo = $esExistente
                && (string) ($data['mantener_asignacion_familiar'] ?? '0') === '1'
                && $esMenorSeleccionado && $edad !== null && $edad >= 18;
            $requiereDni = $forzarDniNuevo || $this->parentescoHijoConDiscapacidadPermanente($parentesco)
                || ($esMenorSeleccionado && (!$esExistente || ($edad !== null && $edad < 18)));
            if (!$requiereDni) continue;

            // Los índices se asignan consecutivamente solo entre hijos, igual que
            // los nombres CODI_PERS_H1/H2, H3/H4 que usa el cargador actual.
            $anverso = $request->file("dni_hijos_anverso.{$numeroHijo}");
            $reverso = $request->file("dni_hijos_reverso.{$numeroHijo}");

            if (!$anverso && $esExistente && $codiPers && !$forzarDniNuevo) {
                $anverso = $this->dniHijoExisteEnServidor($codiPers, $numeroHijo * 2 - 1);
            }
            if (!$reverso && $esExistente && $codiPers && !$forzarDniNuevo) {
                $reverso = $this->dniHijoExisteEnServidor($codiPers, $numeroHijo * 2);
            }

            if (!$anverso || !$reverso) {
                $faltan = [];
                if (!$anverso) $faltan[] = 'anverso';
                if (!$reverso) $faltan[] = 'reverso';
                return [
                    'success' => false,
                    'code' => 'dni_hijo_requerido',
                    'hijo' => $numeroHijo,
                    'message' => 'Falta subir el ' . implode(' y ', $faltan) . ' del DNI del hijo ' . $numeroHijo . '.',
                ];
            }

            foreach ([$anverso, $reverso] as $archivo) {
                if ($archivo instanceof \Illuminate\Http\UploadedFile) {
                    $extension = strtolower((string) $archivo->getClientOriginalExtension());
                    if (!in_array($extension, ['jpg', 'jpeg'], true) || $archivo->getSize() > 10 * 1024 * 1024) {
                        return [
                            'success' => false,
                            'code' => 'dni_hijo_invalido',
                            'hijo' => $numeroHijo,
                            'message' => 'El DNI del hijo ' . $numeroHijo . ' debe estar en JPG y no superar 10 MB por lado.',
                        ];
                    }
                }
            }
        }

        return null;
    }

    private function dniHijoExisteEnServidor(string $codiPers, int $numeroLado): bool
    {
        $nombre = rawurlencode(trim($codiPers) . '_H' . $numeroLado . '.jpg');
        $carpeta = ($numeroLado % 2 === 1) ? 'DNI1_HIJOS' : 'DNI2_HIJOS';
        try {
            return Http::timeout(5)->head("http://190.116.178.163/Biblioteca_Grafica/{$carpeta}/{$nombre}")->successful();
        } catch (\Exception $e) {
            Log::warning('No se pudo verificar DNI de hijo existente', [
                'codi_pers' => $codiPers,
                'lado' => $numeroLado,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * ASIG_FAMI='SI' si hay hijo menor de 18 o hijo mayor incapacitado/con
     * discapacidad permanente. Para hijos mayores sin esa condición es 'NO'.
     */
    private function aplicarAsigFami($codiPers, &$data, string $asigAnterior = 'NO'): array
    {
        $asig = 'NO';
        $hijoMenorCumplio18 = false;

        $evaluar = function ($parentesco, $fechaNaci) use (&$asig, &$hijoMenorCumplio18) {
            $parentesco = mb_strtoupper(trim((string) $parentesco), 'UTF-8');
            if ($this->parentescoHijoConDiscapacidadPermanente($parentesco)) {
                $asig = 'SI';
                return;
            }

            // Solo esta opción se valida además con la fecha de nacimiento.
            if ($parentesco !== 'HIJO MENOR DE EDAD') return;

            $f = trim((string) $fechaNaci);
            if ($f === '') return;
            try {
                $nac = \Carbon\Carbon::createFromFormat('Y-m-d', substr($f, 0, 10));
                if ($nac && $nac->age < 18) {
                    $asig = 'SI';
                } elseif ($nac && $nac->age >= 18) {
                    $hijoMenorCumplio18 = true;
                }
            } catch (\Exception $e) {
                // fecha inválida: no cuenta como menor de edad
            }
        };

        $parentescos = $data['FAM_PARENTESCO'] ?? null;
        $fechas      = $data['FAM_FECHA_NACI'] ?? null;

        if (!empty($data['FAM_PRESENTE']) || (is_array($parentescos) && is_array($fechas))) {
            foreach ((array) $parentescos as $i => $p) {
                $evaluar($p, $fechas[$i] ?? '');
            }
        } elseif (!empty($codiPers)) {
            // Fallback: familias ya registradas en BD
            $rows = DB::select(
                "SELECT TIPO_RELA, CONVERT(varchar(10), FECH_NACI, 23) AS FECH_NACI
                 FROM si_solm.dbo.DERECHO_HABIENTE
                 WHERE CODI_PERS = ? AND UPPER(LTRIM(RTRIM(ISNULL(DEHA_VIGENCIA, 'SI')))) = 'SI'",
                [$codiPers]
            );
            foreach ($rows as $r) {
                $evaluar($r->TIPO_RELA ?? '', $r->FECH_NACI ?? '');
            }
        }

        $asigAnterior = mb_strtoupper(trim($asigAnterior), 'UTF-8');
        $decisionDesactivar = (string) ($data['desactivar_asignacion_familiar'] ?? '0') === '1';
        if ($asigAnterior === 'SI' && $hijoMenorCumplio18) {
            // La mayoría de edad ya no desactiva por sí sola la asignación.
            // Solo se da de baja si el usuario confirma expresamente.
            $asig = $decisionDesactivar ? 'NO' : 'SI';
        }
        $data['ASIG_FAMI'] = $asig;

        // Reflejar en la tabla maestra PERSONAL (DJ2026 se llena vía insertOrUpdate)
        DB::update(
            'UPDATE si_solm.dbo.PERSONAL SET ASIG_FAMI = ? WHERE CODI_PERS = ?',
            [$asig, $codiPers]
        );

        Log::info('aplicarAsigFami', ['CODI_PERS' => $codiPers, 'ASIG_FAMI' => $asig]);

        return [
            'valor' => $asig,
            'desactivada_por_mayoria_edad' => $asigAnterior === 'SI' && $asig === 'NO' && $hijoMenorCumplio18,
        ];
    }

    /**
     * PERS_CONHIJOS indica si hay al menos un familiar registrado como hijo,
     * independientemente de su edad.
     */
    private function aplicarConHijos($codiPers, &$data)
    {
        $hayHijos = false;
        $parentescos = $data['FAM_PARENTESCO'] ?? null;

        if (!empty($data['FAM_PRESENTE']) || is_array($parentescos)) {
            foreach ((array) $parentescos as $parentesco) {
                if (preg_match('/^HIJ/i', trim((string) $parentesco))) {
                    $hayHijos = true;
                    break;
                }
            }
        } elseif (!empty($codiPers)) {
            $rows = DB::select(
                "SELECT TOP 1 1 AS TIENE_HIJOS
                 FROM si_solm.dbo.DERECHO_HABIENTE
                 WHERE CODI_PERS = ? AND UPPER(LTRIM(RTRIM(TIPO_RELA))) LIKE 'HIJ%'
                   AND UPPER(LTRIM(RTRIM(ISNULL(DEHA_VIGENCIA, 'SI')))) = 'SI'",
                [$codiPers]
            );
            $hayHijos = !empty($rows);
        }

        $valor = $hayHijos ? 'SI' : 'NO';
        $data['PERS_CONHIJOS'] = $valor;

        DB::update(
            'UPDATE si_solm.dbo.PERSONAL SET PERS_CONHIJOS = ? WHERE CODI_PERS = ?',
            [$valor, $codiPers]
        );

        Log::info('aplicarConHijos', ['CODI_PERS' => $codiPers, 'PERS_CONHIJOS' => $valor]);
    }

    /**
     * Regla SCTR (columna SCRT en si_solm.dbo.PERSONAL / DJ2026_PERSONAL):
     * - Operativo 4°/5° (01/03)  → 'SI' automático
     * - Administrativo 4°/5° (02/05) → 'SI' si el checkbox autorizar_sctr viene marcado, 'NO' si no
     * - Especial (06) u otro → no se toca
     */
    private function aplicarScrt($codiPers, &$data)
    {
        $tipo = strtoupper(trim((string) ($data['tipo_personal'] ?? '')));

        if (in_array($tipo, ['01', '03'], true)) {
            $scrt = 'SI';
        } elseif (in_array($tipo, ['02', '05'], true)) {
            $scrt = trim((string) ($data['autorizar_sctr'] ?? '')) === '1' ? 'SI' : 'NO';
        } else {
            return; // Especial u otro: no se toca
        }

        $data['SCRT'] = $scrt;

        // Reflejar también en la tabla maestra PERSONAL
        DB::update(
            'UPDATE si_solm.dbo.PERSONAL SET SCRT = ? WHERE CODI_PERS = ?',
            [$scrt, $codiPers]
        );

        Log::info('aplicarScrt', ['CODI_PERS' => $codiPers, 'tipo' => $tipo, 'SCRT' => $scrt]);
    }

    /**
     * Regla de cambio de Tipo de Personal:
     * solo se permite cambiar de Operativo (01/03) a Administrativo (02/05) o viceversa.
     * - Mantener el mismo tipo no se considera cambio (válido).
     * - Si el tipo actual es Especiales (06), no se permite cambiar.
     * - Si no hay tipo previo en BD, no se aplica la regla.
     */
    private function validarCambioTipoPersonal(?string $tipoActual, string $tipoNuevo, bool $esAdminRrhh = false): bool
    {
        $tipoActual = trim((string) $tipoActual);

        if ($tipoActual === '') {
            return true;
        }
        if ($tipoNuevo === $tipoActual) {
            return true;
        }
        if ($tipoActual === '06') {
            return false;
        }

        // Admins RRHH: cualquier cambio permitido excepto a Especial (06)
        if ($esAdminRrhh) {
            return $tipoNuevo !== '06';
        }

        $operativo      = ['01', '03'];
        $administrativo = ['02', '05'];

        if (in_array($tipoActual, $operativo, true)) {
            return in_array($tipoNuevo, $administrativo, true);
        }
        if (in_array($tipoActual, $administrativo, true)) {
            return in_array($tipoNuevo, $operativo, true);
        }

        return true;
    }

    public function saveRecontratacion(Request $request)
    {
        DB::beginTransaction();
 
        try {
            $codiPers = trim($request->input('cod_postulante', ''));
 
            if (empty($codiPers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo identificar el código de personal para recontratación.',
                ], 400);
            }
 
            $personal = DB::selectOne(
                "SELECT CODI_PERS, NRO_DOCU_IDEN, PERS_VIGENCIA, PERS_TIPOTRAB, ASIG_FAMI FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?",
                [$codiPers]
            );

            if (!$personal) {
                return response()->json(['success' => false, 'message' => "No se encontró el personal con código {$codiPers}."], 404);
            }

            if ($personal->PERS_VIGENCIA === 'SI') {
                return response()->json(['success' => false, 'message' => "El personal {$codiPers} ya está VIGENTE."], 422);
            }

            $data = $request->all();

            // Si el usuario confirmó la baja por mayoría de edad, exigir fotos
            // nuevas de ambos lados aunque ya existan archivos del contrato anterior.
            $errorDniHijos = $this->validarDniHijosRequeridos($request, $data, (string) $codiPers, true);
            if ($errorDniHijos) {
                DB::rollBack();
                return response()->json($errorDniHijos, 422);
            }

            // Fecha de Ingreso a Solmar: OBLIGATORIA en recontratación
            // (es el nuevo ingreso; no se hereda la fecha anterior)
            if (trim((string) ($data['fecha_ingreso_solmar'] ?? '')) === '') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'La Fecha de Ingreso a Solmar es obligatoria para la recontratación.',
                ], 422);
            }

            $tipoPer = trim($data['tipo_personal'] ?? '');
            if ($tipoPer === '') {
                // Si no se envía tipo, se mantiene el actual del personal.
                $tipoPer = trim((string)($personal->PERS_TIPOTRAB ?? ''));
            }

            // Solo usuarios autorizados pueden cambiar el tipo de personal.
            $usuarioTipoPer = strtoupper(trim((string)(session('usuario') ?? '')));
            $tipoActual = trim((string)($personal->PERS_TIPOTRAB ?? ''));

            if ($tipoPer !== $tipoActual) {
                if (!in_array('cambiar_tipo_personal', session('funcionalidades', []))) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'No tiene permisos para modificar el tipo de personal.'], 403);
                }
            }

            if (!$this->validarCambioTipoPersonal($tipoActual, $tipoPer, in_array('cambiar_tipo_personal', session('funcionalidades', [])))) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'No está permitido ese cambio de tipo de personal.'], 422);
            }
 
            // ── Documentos obligatorios: foto del personal + DNI (anverso y reverso) ──
            // MISMA REGLA que saveNuevaDj: sin foto/DNI no se recontrata.
            // Diferencia: aquí la persona ya pudo haber dejado imágenes del contrato
            // anterior, así que se exige solo lo que FALTE en el servidor de archivos
            // (mismo criterio que saveDjCompleto). Si el servidor de archivos no
            // responde no se bloquea el guardado. Los usuarios con
            // "exento_validaciones" no están obligados a adjuntarlos.
            $exentoValidaciones = in_array('exento_validaciones', session('funcionalidades', []));
            $fotoReq            = $request->file('foto');
            $dniAnversoReq      = $request->file('dni_anverso');
            $dniReversoReq      = $request->file('dni_reverso');

            if (!$exentoValidaciones) {
                $estadoFoto = $this->fotoPersonalEnServidor($codiPers);
                $estadoDni  = $this->dniPersonalEnServidor($codiPers);

                $faltaFoto = ($estadoFoto === false && !$fotoReq);

                $faltanLados = [];
                if ($estadoDni['consultado']) {
                    if (!$estadoDni['anverso'] && !$dniAnversoReq) $faltanLados[] = 'anverso';
                    if (!$estadoDni['reverso'] && !$dniReversoReq) $faltanLados[] = 'reverso';
                }

                $faltanPartes = [];
                if ($faltaFoto)           $faltanPartes[] = 'foto del personal';
                if (!empty($faltanLados)) $faltanPartes[] = 'foto del DNI';

                if (!empty($faltanPartes)) {
                    DB::rollBack();
                    return response()->json([
                        'success'        => false,
                        'code'           => $faltaFoto ? 'falta_foto' : 'falta_dni',
                        'message'        => 'Falta ingresar ' . implode(' y ', $faltanPartes) . '.',
                        'falta_foto'     => $faltaFoto,
                        'falta_anverso'  => in_array('anverso', $faltanLados, true),
                        'falta_reverso'  => in_array('reverso', $faltanLados, true),
                    ], 422);
                }
            }

            foreach ([$fotoReq, $dniAnversoReq, $dniReversoReq] as $archivo) {
                if (!$archivo) {
                    continue;
                }
                $extension = strtolower((string) $archivo->getClientOriginalExtension());
                if (!in_array($extension, ['jpg', 'jpeg'], true)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'code'    => 'documento_invalido',
                        'message' => 'La foto del personal y la del DNI deben ser imágenes JPG.',
                    ], 422);
                }
                if ($archivo->getSize() > 10 * 1024 * 1024) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'code'    => 'documento_invalido',
                        'message' => 'Cada imagen no debe superar los 10 MB.',
                    ], 422);
                }
            }

            $str2 = fn($v) => strtoupper(substr(trim($v ?? ''), 0, 2));
            $str1 = fn($v) => strtoupper(substr(trim($v ?? ''), 0, 1));
            $trim = fn($v) => strlen(trim($v ?? '')) ? trim($v) : null;
            $num  = fn($v) => is_numeric($v ?? null) ? $v : null;
            $intv = fn($v) => is_numeric($v ?? null) ? (int)$v : null;
 
            $sexo        = $str1($data['sexo']         ?? 'M');
            $essalud     = $str2($data['essalud']       ?? 'NO');
            $pensionista = $str2($data['pensionista']   ?? 'NO');
            $embargo     = $str2($data['embargos']      ?? 'NO');
            $condiscamec = $str2($data['curso_sucamec'] ?? 'NO');
            $conarmas    = $str2($data['arma_propia']   ?? 'NO');
            $vehiculoP   = $str2($data['vehiculo_propio']  ?? 'NO');
            $famEmpresa  = $str2($data['familiar_empresa'] ?? 'NO');
            $claseBrev   = !empty($data['clase_brevete']) ? strtoupper(substr($data['clase_brevete'], 0, 1)) : null;
 
            $fechaNaci   = $this->sanitizeDatetimeForPersonal($data['fecha_nacimiento'] ?? null);
            $fechaCaduca = $this->sanitizeDatetimeForPersonal($data['caduca']           ?? null);
            $fechaIngre  = $this->sanitizeDatetimeForPersonal($data['fecha_ingreso_solmar'] ?? null);
 
            $estadoCivilMap   = ['2007000001'=>'S','2007000002'=>'C','2007000003'=>'D','2007000004'=>'V','2007000008'=>'V'];
            $estadoCivil      = $data['estado_civil'] ?? null;
            $estadoCivilCorto = $estadoCivilMap[$estadoCivil] ?? null;
 
$tipotrab    = $tipoPer;

            // TIPO_CONT: O = Operativo/Especial, A = Administrativo
            $tipoContMap = ['01' => 'O', '02' => 'A', '03' => 'O', '05' => 'A', '06' => 'O'];
            $tipoCont = $tipoContMap[$tipoPer] ?? 'O';
  
            $carrCodigo = null;
            $ieduCodigo = null;
            if (!empty($data['carrera']) && $data['carrera'] !== '999999') {
                $carreraExiste = DB::selectOne(
                    'SELECT 1 FROM si_solm.dbo.SUNAT_CARRERAS WHERE CARR_CODIGO = ? AND IEDU_CODIGO = ?',
                    [$data['carrera'], $data['institucion'] ?? null]
                );
                if ($carreraExiste) { $carrCodigo = $data['carrera']; $ieduCodigo = $data['institucion'] ?? null; }
            }
 
            $laboral1      = !empty(trim($data['dj2026_laboral_1'] ?? '')) ? strtoupper(trim($data['dj2026_laboral_1'])) : null;
            $laboral2      = !empty(trim($data['dj2026_laboral_2'] ?? '')) ? strtoupper(trim($data['dj2026_laboral_2'])) : null;
            $cantProfesion = ($laboral1 ? 1 : 0) + ($laboral2 ? 1 : 0);

             $sucursal = !empty(trim($data['sucursal'] ?? '')) ? strtoupper(trim($data['sucursal'])) : null;
            $usuario = !empty(trim($data['usuario'] ?? '')) ? strtoupper(trim($data['usuario'])) : null;
            $codiUnidOper = null;
            if (!empty($sucursal)) {
                $uo = DB::selectOne(
                    'SELECT TOP 1 CODI_UNID_OPER FROM si_solm.dbo.UNID_OPER WHERE UBICACION = ?',
                    [$sucursal]
                );
                $codiUnidOper = $uo->CODI_UNID_OPER ?? null;
            }
            $experienciaMeses = $intv($data['experiencia_meses'] ?? null);
 
            DB::update(
                "UPDATE si_solm.dbo.PERSONAL SET
                    PERS_VIGENCIA='SI', PERS_CONTRATADO='1',
                    NOMB_1=?, NOMB_2=?, APEL_1=?, APEL_2=?,
                    SEXO=?, PERS_SEXO=?,
                    ESCI_CODIGO=?, ESTA_CIVI=?,
                    FECH_NACI=?, PERS_FECHCADUCADNI=?,
                    PERS_EMAIL=?, PERS_TELEFONO=?, PERS_WHATSAPP=?,
                    DIRECCION=?, PERS_DIREC_DNI=?, TIZO_CODIGO=?, PERS_ZONA_DIRDNI=?,
                    PERS_DEPT_ACT=?, PERS_PROV_ACT=?, PERS_DIST_ACT=?,
                    PERS_DPTO_DIRDNI=?, PERS_PROV_DIRDNI=?, PERS_DIST_DIRDNI=?,
                    DEPARTAMENTO=?, PROVINCIA=?, DISTRITO=?,
                    DEPA_CODIGO_NACI=?, PROVI_CODIGO_NACI=?, DIST_NACI=?,
                    tipo_sangr=?, peso_kilo=?, tall_metr=?,
                    CODI_SIST_PENS=?, ESSALUD=?, PERS_PENSIONISTA=?, PERS_EMBARGO=?,
                    PERS_GRADO_INSTRUCCION=?, CARR_CODIGO=?, IEDU_CODIGO=?, EGRESO_EDUCATIVO=?,
                    PERS_CONDISCAMEC=?, PERS_NRODISCAMEC=?,
                    PERS_LUGARSMO=?, PERS_CONARMAS=?, PERS_NROLICENCIA=?,
                    PERS_BREVETE=?, CLASE_BREVETE=?, CATEGORIA_BREVETE=?, PERS_VEHICULO_PROPIO=?,
                    PERS_NOMCONTACTO=?, PERS_NROEMERGENCIA=?, PERS_EMERC_FAMILIAR=?,
                    PERS_CTRABANT=?, PERS_CARGOTRABANT=?, PERS_DURACIONANT=?,
                    dj2026_banco=?, NACIONALIDAD=?, dj2026_ocupacion_principal=?,
                    dj2026_experiencia_anios=?, dj2026_experiencia_meses=?, dj2026_familiar_empresa=?,
                    dj2026_familiar_nombre=?, dj2026_familiar_parentesco=?,
                    dj2026_laboral_1=?, dj2026_laboral_2=?, dj2026_cantprofesion=?,
                    PERS_TIPOTRAB=?, CODI_CARG=?, USUA_FECHA_MOD=GETDATE(), SUCU_CODIGO = ?, CODI_UNID_OPER = ?, USUA_CODIGO_REG = ?, EMPR_CODIGO = '01',
                    TIPO_CONT=?, FECH_INGRE=?, NO_CADUCA_DNI=?, PERS_CONSMO=?
                WHERE CODI_PERS=?",
                [
                    strtoupper(trim((string) ($data['nombre1'] ?? ''))) ?: null,
                    strtoupper(trim((string) ($data['nombre2'] ?? ''))) ?: null,
                    strtoupper(trim((string) ($data['apellido_paterno'] ?? ''))) ?: null,
                    strtoupper(trim((string) ($data['apellido_materno'] ?? ''))) ?: null,
                    $sexo, $sexo,
                    $estadoCivil, $estadoCivilCorto,
                    $fechaNaci, $fechaCaduca,
                    $trim($data['correo']   ?? null), $trim($data['celular'] ?? null), $trim($data['whatsapp'] ?? null),
                    $trim($data['direccion_actual'] ?? null), $trim($data['direccion_dni'] ?? null), $trim($data['tipo_zona_dni'] ?? null), $trim($data['zona_dirdni'] ?? null),
                    $trim($data['departamento_actual'] ?? null), $trim($data['provincia_actual'] ?? null), $trim($data['distrito_actual'] ?? null),
                    $trim($data['departamento_dni']  ?? null), $trim($data['provincia_dni']    ?? null), $trim($data['distrito_dni']    ?? null),
                    strtoupper(trim($data['departamento_actual'] ?? '') ?: ''), strtoupper(trim($data['provincia_actual'] ?? '') ?: ''), strtoupper(trim($data['distrito_actual'] ?? '') ?: ''),
                    $trim($data['departamento_nac']  ?? null), $trim($data['provincia_nac']    ?? null), $trim($data['distrito_nac']    ?? null),
                    $trim($data['tipo_sangre'] ?? null), $num($data['peso'] ?? null), $num($data['talla'] ?? null),
                    $trim($data['sistema_previsional'] ?? null), $essalud, $pensionista, $embargo,
                    $trim($data['grado_instruccion'] ?? null), $carrCodigo, $ieduCodigo, $intv($data['anio_egreso'] ?? null),
                    $condiscamec, $trim($data['sucamec_obs'] ?? null),
                    strtoupper(trim($data['lugar_smo'] ?? '')) ?: null, $conarmas, $trim($data['licencia_arma'] ?? null),
                    $trim($data['brevete'] ?? null), $claseBrev, $trim($data['tipo_vehiculo'] ?? null), $vehiculoP,
                    $trim($data['contacto_emergencia'] ?? null), $trim($data['celular_emergencia'] ?? null), $trim($data['parentesco_emergencia'] ?? null),
                    $trim($data['empresa_anterior'] ?? null), $trim($data['cargo_anterior'] ?? null), $trim($data['duracion_anterior'] ?? null),
                    $trim($data['cuenta_banco'] ?? null),
                    !empty(trim($data['nacionalidad'] ?? '')) ? strtoupper(trim($data['nacionalidad'])) : null,
                    $trim($data['ocupacion_principal'] ?? null),
                    $intv($data['experiencia_anios'] ?? null), $experienciaMeses, $famEmpresa,
                    $trim($data['familiar_nombre'] ?? null), $trim($data['familiar_parentesco'] ?? null),
                    $laboral1, $laboral2, $cantProfesion,
                    //$tipotrab,
                    $tipoPer,
                    $trim($data['cargo'] ?? null),
                    $sucursal,
                    $codiUnidOper,
                    $usuario,
                    $tipoCont,
                    $fechaIngre,
                    ($data['no_caduca_dni'] ?? '0') === '1' ? 1 : 0,
                    strtoupper(trim($data['presto_smo'] ?? 'NO')),
                    $codiPers
                ]
            );
 
            $this->saveFamiliaresTemp($codiPers, $data);
            $this->migrarFamiliaresConVigencia($codiPers, $data);
            $this->saveTelefonosTemp($codiPers, $data);
            $this->migrarTelefonos($codiPers);

            // Actualizar DJ2026_PERSONAL con los datos de la recontratación (sucursal, fecha modificación, etc.)
            // SCTR: OP (01/03) → 'SI' automático; ADMIN (02/05) → según checkbox
            $this->aplicarScrt($codiPers, $data);
            $this->aplicarAsigFami($codiPers, $data, (string) ($personal->ASIG_FAMI ?? ''));
            $this->aplicarConHijos($codiPers, $data);
            $this->insertOrUpdateDJ2026Personal($codiPers, $data, 'recontratacion');

            // ── Subir foto y DNI al servidor de archivos ──────────────────
            // MISMO FLUJO que saveNuevaDj: si el servidor de archivos falla NO
            // se bloquea la recontratación; se guarda igual y se avisa qué
            // quedó pendiente de subir (foto_pendiente / dni_pendiente).
            $subidaFoto    = $this->subirFotoAlServidor($codiPers, $fotoReq);
            $subidaDni     = $this->subirDniAlServidor($codiPers, $dniAnversoReq, $dniReversoReq);
            $fotoPendiente = !$subidaFoto['ok'];
            $dniPendiente  = !$subidaDni['ok'];
 
            DB::commit();
 
            Log::info('saveRecontratacion: Personal recontratado', ['CODI_PERS' => $codiPers, 'DNI' => $personal->NRO_DOCU_IDEN]);

            // ── Correo automático de bienvenida SIP (solo Operativo 5° y Administrativo 5°) ──
            if (in_array($tipoPer, ['03', '05'])) {
                $this->enviarCorreoBienvenidaSip($data, $personal->NRO_DOCU_IDEN, $codiPers);
            }
 
            $pendientesMsg = [];
            if ($fotoPendiente) $pendientesMsg[] = 'la foto del personal';
            if ($dniPendiente)  $pendientesMsg[] = 'el DNI';

            return response()->json([
                'success'        => true,
                'message'        => !empty($pendientesMsg)
                    ? 'Recontratación realizada correctamente, pero ' . implode(' y ', $pendientesMsg) . ' quedó pendiente de subir.'
                    : 'Recontratación realizada correctamente. El personal está VIGENTE nuevamente.',
                'codi_pers'      => $codiPers,
                'foto_pendiente' => $fotoPendiente,
                'dni_pendiente'  => $dniPendiente,
                'dni_faltan'     => $subidaDni['faltan'],
            ]);
 
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en saveRecontratacion: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al procesar la recontratación: ' . $e->getMessage()], 500);
        }
    }



    /**
     * Obtener estado CHECK_PDF de todos los migrados
     */
    public function getCheckPdf(Request $request)
    {
        try {
            $codPeriodo = $request->input('codPeriodo', '2026');

            $data = DB::table('sisolm_web.dbo.sw_dj_generada_x_periodo')
                ->where('codPeriodo', $codPeriodo)
                ->where('habilitado', 1)
                ->where('generado', 1)
                ->select('codPersonal', 'generado', 'fechaCreacion', 'fechaModificacion', 'creadoPor', 'modificadoPor')
                ->get()
                ->keyBy('codPersonal');

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error en getCheckPdf: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function buscarCoincidencias(Request $request)
    {
        $nomb1 = $request->input('nomb1');
        $nomb2 = $request->input('nomb2');
        $apel1 = $request->input('apel1');
        $apel2 = $request->input('apel2');

       $coincidencias = DB::table('si_solm.dbo.PERSONAL')
        ->select('NOMB_1', 'NOMB_2', 'APEL_1', 'APEL_2', 'NRO_DOCU_IDEN', 'PERS_VIGENCIA')
        ->where(function($q) use ($nomb1, $apel1, $apel2, $nomb2) {


            $q->where(function($sub) use ($nomb1, $apel1, $apel2) {
                if ($nomb1) $sub->where('NOMB_1', 'like', "%$nomb1%");
                if ($apel1) $sub->where('APEL_1', 'like', "%$apel1%");
                if ($apel2) $sub->where('APEL_2', 'like', "%$apel2%");
            });

            if ($nomb1 || $nomb2) {
                $q->orWhere(function($sub) use ($nomb1, $nomb2) {
                if ($nomb1) $sub->where('NOMB_1', 'like', "%$nomb1%");
                if ($nomb2) $sub->where('NOMB_2', 'like', "%$nomb1%");
            });
            }

            if ($apel1 || $apel2) {
                $q->orWhere(function($sub) use ($apel1, $apel2) {
                    if ($apel1) $sub->where('APEL_1', 'like', "%$apel1%");
                    if ($apel2) $sub->where('APEL_2', 'like', "%$apel2%");
                });
            }
        })
        ->limit(5)
        ->get();

        return response()->json(['data' => $coincidencias]);
    }

  
    public function validarDocumentoDj(Request $request)
    {
        try {
            $nro = $request->get('numero');
            $tipo = $request->get('tipo');
            $data = DB::select(
                "SELECT TOP 1 PERS_VIGENCIA, CODI_PERS as codigo , NOMB_1, NOMB_2, APEL_1, APEL_2, NRO_DOCU_IDEN
                FROM si_solm.dbo.PERSONAL WHERE NRO_DOCU_IDEN = ? AND CODI_TIPO_DOCU = ?", [$nro, $tipo]
            );

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error en getTipoDoc: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


                    
    /**
     * Obtener tipode documento de forma generar para el DJ
     */
    public function getTipoDoc()
    {
        try {
            $data = DB::select(
                "SELECT CODI_TIPO_DOCU AS codigo, UPPER(DESC_TIPO_DOCU) AS nombre 
                FROM si_solm.dbo.TIPO_DOCUMENTO WITH (NOLOCK) WHERE CODI_TIPO_DOCU IN ('0034', '0035', '0037', '0216', '0215', '0038')"
            );

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error en getTipoDoc: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    public function getEstadoCivil()
    {
        try {
            $data = DB::select(
                "SELECT ESCI_CODIGO AS codigo, ESCI_DESCRIPCION AS nombre FROM si_solm.dbo.ADMI_ESTADO_CIVIL WHERE ESCI_VIGENCIA = 'SI'"
            );

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error en getTipoDoc: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


     public function getSistemaPrev()
    {
        try {
            $data = DB::select(
                "SELECT 
                    CODI_SIST_PENS AS codigo, 
                    CASE
                        WHEN CODI_SIST_PENS = '01' THEN 'ONP' ELSE DESC_SIST_PENS
                    END AS nombre
                FROM si_solm.dbo.SISTEMA_PENSIONES
                WHERE SIPE_VIGENCIA = 'SI'
                ORDER BY 
                    CASE CODI_SIST_PENS
                        WHEN '03' THEN 1 -- AFP PROFUTURO
                        WHEN '01' THEN 2 -- ONP
                        WHEN '02' THEN 3 -- AFP INTEGRA
                        WHEN '10' THEN 4 -- AFP PRIMA
                        WHEN '27' THEN 5 -- AFP HABITAT
                        WHEN '07' THEN 6 -- NO APORTACION
                        ELSE 99
                    END;"
            );

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error en getTipoDoc: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

     public function getTipoPer()
    {
        try {
            $data = DB::select(
                "SELECT TIPE_CODIGO AS codigo, TIPE_DESCRIPCION AS nombre FROM si_solm.dbo.ADMI_TIPO_PERSONAL"
            );

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error en getTipoDoc: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getCargosDj()
    {
        try {
            $data = DB::select(
                "SELECT CODI_CARG AS codigo, DESC_CARGO AS nombre, CARGO_TIPO AS tipo
                 FROM si_solm.dbo.CARGOS
                 WHERE CARG_VIGENCIA = 'SI' AND DESC_CARGO IS NOT NULL
                 ORDER BY DESC_CARGO"
            );

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('Error en getCargosDj: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function verificarContrato(Request $request)
    {
        $codiPers = trim($request->get('codi_pers') ?? '');

        if ($codiPers === '') {
            return response()->json(['success' => false, 'message' => 'Código de personal requerido'], 400);
        }

        try {
            $persona = DB::selectOne(
                'SELECT PERS_CONTRATADO FROM si_solm.dbo.PERSONAL WHERE CODI_PERS = ?',
                [$codiPers]
            );

            $tieneContrato = false;
            if ($persona && isset($persona->PERS_CONTRATADO)) {
                $tieneContrato = (int)$persona->PERS_CONTRATADO === 1;
            }

            return response()->json([
                'success'       => true,
                'tiene_contrato' => $tieneContrato,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en verificarContrato: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al verificar contrato'], 500);
        }
    }

    public function verificarVacaciones(Request $request)
    {
        $codiPers = trim($request->get('codi_pers') ?? '');

        if ($codiPers === '') {
            return response()->json(['success' => false, 'message' => 'Código de personal requerido'], 400);
        }

        try {
            $enVacaciones = false;

            // 1. VACACIONES (registro maestro) con rango vigente a hoy
            $vaca = DB::selectOne(
                'SELECT TOP 1 1 AS x FROM si_solm.dbo.VACACIONES
                 WHERE CODI_PERS = ? AND VACA_FEC_INI <= GETDATE() AND VACA_FEC_FIN >= GETDATE()',
                [$codiPers]
            );
            if ($vaca) {
                $enVacaciones = true;
            }

            // 2. VACACIONES_CRONOGRAMA (detalle) vinculado a VACACIONES, con rango vigente
            if (! $enVacaciones) {
                $crono = DB::selectOne(
                    'SELECT TOP 1 1 AS x
                     FROM si_solm.dbo.VACACIONES_CRONOGRAMA cr
                     INNER JOIN si_solm.dbo.VACACIONES v ON v.VACA_CODIGO = cr.VACA_CODIGO
                     WHERE v.CODI_PERS = ?
                       AND cr.VACR_FEC_INI <= GETDATE()
                       AND (cr.VACR_FEC_FIN >= GETDATE() OR cr.VACR_FEC_RETORNO >= GETDATE())',
                    [$codiPers]
                );
                if ($crono) {
                    $enVacaciones = true;
                }
            }

            return response()->json([
                'success'       => true,
                'en_vacaciones' => $enVacaciones,
            ]);
        } catch (\Exception $e) {
            Log::error('Error en verificarVacaciones: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Error al verificar vacaciones'], 500);
        }
    }

    public function reglasEdad()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->obtenerReglasEdad(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener reglas de edad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar las reglas de edad.',
            ], 500);
        }
    }

    public function validarExcepcionEdad(Request $request)
    {
        $usuario = strtoupper(trim($request->input('usuario', '')));
        $clave = (string) $request->input('clave', '');
        $fechaNacimiento = trim($request->input('fecha_nacimiento', ''));

        if ($usuario === '' || $clave === '' || $fechaNacimiento === '') {
            return response()->json([
                'success' => false,
                'code' => 'datos_incompletos',
                'message' => 'Usuario, contraseña y fecha de nacimiento son obligatorios.',
            ], 422);
        }

        // La cuenta ingresada puede ser DISTINTA a la del usuario que registra la DJ:
        // quien autoriza debe ser un ADMIN RRHH (rol tipo_rol = 17).
        $user = DB::table('sw_usuarios')
            ->where('usuario', $usuario)
            ->where('habilitado', 1)
            ->first();

        $hashAlmacenado = $user ? rtrim((string) $user->clave) : '';
        if (!$user || !Hash::check($clave, $hashAlmacenado)) {
            return response()->json([
                'success' => false,
                'code' => 'clave_invalida',
                'message' => 'La contraseña no es válida.',
            ], 422);
        }

        // Solo el rol ADMINS RRHH (tipo_rol 17) puede autorizar excepciones de edad.
        if ((int) $user->tipo_rol !== self::ROL_ADMIN_RRHH) {
            return response()->json([
                'success' => false,
                'code' => 'sin_rol_admin_rrhh',
                'message' => 'La cuenta ingresada no tiene el rol ADMINS RRHH. Un administrador de RRHH debe autorizar la excepción.',
            ], 403);
        }

        $fecha = $this->parsearFechaNacimiento($fechaNacimiento);
        if (!$fecha) {
            return response()->json([
                'success' => false,
                'code' => 'fecha_nacimiento_invalida',
                'message' => 'La fecha de nacimiento no es válida.',
            ], 422);
        }

        try {
            $reglasEdad = $this->obtenerReglasEdad();
        } catch (\Throwable $e) {
            Log::error('Error al validar excepción de edad: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'code' => 'reglas_edad_no_disponibles',
                'message' => $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : 'No se pudieron cargar las reglas de edad para validar la excepción.',
            ], 422);
        }

        $edad = $fecha->age;

        if ($edad >= $reglasEdad['minima'] && $edad <= $reglasEdad['maxima']) {
            return response()->json([
                'success' => false,
                'code' => 'excepcion_no_requerida',
                'message' => 'La edad está dentro del rango y no requiere excepción.',
            ], 422);
        }

        session([
            'dj_excepcion_edad' => [
                'fecha_nacimiento' => $fecha->format('Y-m-d'),
                'edad' => $edad,
                'usuario_autorizador' => $usuario,
                'fecha_autorizacion' => now(),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Credenciales validadas. Puede continuar con el registro.',
        ]);
    }

    public function getUsuariosExcepcionEdad()
    {
        $denegado = $this->denegadoSiNoEsAdminRrhh();
        if ($denegado !== null) {
            return $denegado;
        }

        try {
            $usuarios = DB::connection('sqlsrv')->select(
                "SELECT u.usuario, u.nombre_1, u.apellido_1,
                        CASE WHEN p.usuario IS NOT NULL THEN 1 ELSE 0 END AS habilitado
                 FROM sisolm_web.dbo.sw_usuarios u
                 LEFT JOIN sisolm_web.dbo.sw_permisos_excepcion_edad p ON u.usuario = p.usuario AND p.habilitado = 1
                 WHERE u.habilitado = 1
                 ORDER BY u.usuario"
            );

            return response()->json(['success' => true, 'data' => $usuarios]);
        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios excepción edad: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function saveUsuariosExcepcionEdad(Request $request)
    {
        $denegado = $this->denegadoSiNoEsAdminRrhh();
        if ($denegado !== null) {
            return $denegado;
        }

        try {
            $usuarios = $request->input('usuarios', []);

            DB::connection('sqlsrv')->unprepared('DELETE FROM sisolm_web.dbo.sw_permisos_excepcion_edad');

            if (!empty($usuarios)) {
                $values = [];
                foreach ($usuarios as $usuario) {
                    $usuario = strtoupper(trim(addslashes($usuario)));
                    if ($usuario !== '') {
                        $values[] = "('$usuario')";
                    }
                }
                if (!empty($values)) {
                    DB::connection('sqlsrv')->unprepared(
                        'INSERT INTO sisolm_web.dbo.sw_permisos_excepcion_edad (usuario) VALUES ' . implode(', ', $values)
                    );
                }
            }

            return response()->json(['success' => true, 'message' => 'Permisos actualizados correctamente.']);
        } catch (\Exception $e) {
            Log::error('Error al guardar usuarios excepción edad: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * ¿La cuenta tiene el rol ADMINS RRHH?
     */
    private function esAdminRrhh(string $usuario): bool
    {
        $usuario = strtoupper(trim($usuario));
        if ($usuario === '') {
            return false;
        }

        $fila = DB::table('sw_usuarios')
            ->where('usuario', $usuario)
            ->where('habilitado', 1)
            ->first();

        return $fila !== null && (int) $fila->tipo_rol === self::ROL_ADMIN_RRHH;
    }

    /**
     * Determina si hay una excepción de edad válida para la fecha/edad indicadas.
     *
     * Solo se acepta la autorización validada en el modal (sesión 'dj_excepcion_edad')
     * y, además, se exige que quien autorizó siga teniendo el rol ADMINS RRHH.
     * La autorización se pide en cada registro Y en cada edición de la DJ.
     */
    private function excepcionEdadAutorizada(string $fechaNacimiento, int $edad): bool
    {
        $excepcionEdad = session('dj_excepcion_edad');
        $sesionValida = is_array($excepcionEdad)
            && ($excepcionEdad['fecha_nacimiento'] ?? null) === $fechaNacimiento
            && (int) ($excepcionEdad['edad'] ?? -1) === $edad
            && ($excepcionEdad['usuario_autorizador'] ?? '') !== '';

        return $sesionValida && $this->esAdminRrhh((string) $excepcionEdad['usuario_autorizador']);
    }

    /**
     * Devuelve una respuesta 403 si quien hace la petición no es ADMIN RRHH.
     * Se usa para proteger los endpoints de administración de excepciones de edad.
     */
    private function denegadoSiNoEsAdminRrhh(): ?\Illuminate\Http\JsonResponse
    {
        if ($this->esAdminRrhh((string) (session('usuario') ?? ''))) {
            return null;
        }

        return response()->json([
            'success' => false,
            'code' => 'sin_rol_admin_rrhh',
            'message' => 'Solo un usuario con rol ADMINS RRHH puede administrar los permisos de excepción de edad.',
        ], 403);
    }

    /**
     * Registra la auditoría de la excepción de edad y notifica a RRHH.
     * Se ejecuta dentro de la transacción, después de guardar el personal.
     */
    private function registrarExcepcionEdad(
        string $codPersonal,
        string $dni,
        string $fechaNacimiento,
        int $edad,
        array $reglasEdad,
        array $data,
        ?string $tipoTrab,
        ?string $sucursal
    ): void {
        $excepcionEdad = session('dj_excepcion_edad');
        $tipoExcepcion = $edad < $reglasEdad['minima'] ? 'MINIMA' : 'MAXIMA';
        $codigoRegla   = $edad < $reglasEdad['minima'] ? 377 : 176;

        $now       = now()->format('Y-m-d H:i:s');
        $fechaNac  = \Carbon\Carbon::parse($fechaNacimiento)->format('Y-m-d');
        $fechaAuto = \Carbon\Carbon::parse($excepcionEdad['fecha_autorizacion'])->format('Y-m-d H:i:s');

        $codPersonalQ = addslashes((string) $codPersonal);
        $dniVal       = addslashes((string) $dni);
        $tipoExc      = addslashes((string) $tipoExcepcion);
        $usuReg       = addslashes((string) (session('usuario') ?? '0'));
        $usuAutor     = addslashes((string) ($excepcionEdad['usuario_autorizador'] ?? ''));

        DB::connection('sqlsrv')->unprepared(
            "INSERT INTO sisolm_web.dbo.sw_dj_excepciones_edad 
                (cod_personal, dni, fecha_nacimiento, edad_calculada, tipo_excepcion, 
                 codigo_valo_unitario, edad_minima, edad_maxima, usuario_registro, 
                 usuario_autorizador, fecha_autorizacion, fecha_registro) 
             VALUES ('$codPersonalQ', '$dniVal', CONVERT(date,'$fechaNac',23), $edad, '$tipoExc', $codigoRegla, {$reglasEdad['minima']}, {$reglasEdad['maxima']}, '$usuReg', '$usuAutor', CONVERT(datetime,'$fechaAuto',121), CONVERT(datetime,'$now',121))"
        );

        session()->forget('dj_excepcion_edad');

        $nombreCompleto = trim(($data['nombre1'] ?? '') . ' ' . ($data['nombre2'] ?? '') . ' ' . ($data['apellido_paterno'] ?? '') . ' ' . ($data['apellido_materno'] ?? ''));

        // Resolver tipo trabajador
        $tipoTrabNombre = $tipoTrab;
        if (!empty($tipoTrab)) {
            $tipoRow = DB::connection('sqlsrv')->selectOne(
                'SELECT TIPE_DESCRIPCION FROM si_solm.dbo.ADMI_TIPO_PERSONAL WHERE TIPE_CODIGO = ?',
                [$tipoTrab]
            );
            $tipoTrabNombre = $tipoRow->TIPE_DESCRIPCION ?? $tipoTrab;
        }

        // Resolver sucursal
        $sucursalNombre = $sucursal;
        if (!empty($sucursal)) {
            $sucRow = DB::connection('sqlsrv')->selectOne(
                'SELECT SUCU_ABREVIATURA FROM si_solm.dbo.SISO_SUCURSAL WHERE SUCU_CODIGO = ?',
                [$sucursal]
            );
            $sucursalNombre = $sucRow->SUCU_ABREVIATURA ?? $sucursal;
        }

        $datosCorreo = [
            'nombre'              => $nombreCompleto,
            'dni'                 => $dni,
            'fecha_nacimiento'    => $fechaNacimiento,
            'edad'                => $edad,
            'tipo_excepcion'      => $tipoExcepcion,
            'edad_minima'         => $reglasEdad['minima'],
            'edad_maxima'         => $reglasEdad['maxima'],
            'tipo_trabajador'     => $tipoTrabNombre,
            'sucursal'            => $sucursalNombre,
            'usuario_registro'    => session('usuario') ?? 'N/A',
            'usuario_autorizador' => $excepcionEdad['usuario_autorizador'] ?? '',
            'fecha_autorizacion'  => $excepcionEdad['fecha_autorizacion'] ?? null,
        ];

        Mail::to('rrhh@solsecurity.pe')->send(new ExcepcionEdadMail($datosCorreo));
    }

    /**
     * Sube el DNI (anverso y/o reverso) al servidor de archivos.
     * No lanza excepción: devuelve ['ok' => bool, 'faltan' => [lados fallidos]] y
     * el llamador decide si el fallo bloquea el guardado o solo avisa.
     */
    private function subirDniAlServidor(?string $codiPers, $anverso = null, $reverso = null): array
    {
        $codiPers = trim((string) $codiPers);
        $pendientes = [];
        if ($anverso) $pendientes['anverso'] = ['file' => $anverso, 'ruta' => 'DNI1_1'];
        if ($reverso) $pendientes['reverso'] = ['file' => $reverso, 'ruta' => 'DNI2_1'];

        if ($codiPers === '' || empty($pendientes)) {
            return ['ok' => empty($pendientes), 'faltan' => array_keys($pendientes)];
        }

        // Nombre fijo: CODI_PERS.jpg (así lo muestra el visor existente)
        $nameFile = $codiPers . '.jpg';
        $fallos   = [];

        foreach ($pendientes as $lado => $info) {
            try {
                $response = Http::withToken('457862h45hj7u5126h58d2s51s2s')
                    ->attach('archivo', file_get_contents($info['file']->getRealPath()), $nameFile)
                    ->post('http://190.116.178.163/apps/api/file-control/charge_file.php', [
                        'nameFile' => $nameFile,
                        'ruta'     => $info['ruta'],
                    ]);

                $proxyData = $response->json();
                if ($response->failed() || (isset($proxyData['success']) && $proxyData['success'] === false)) {
                    $fallos[] = $lado;
                    Log::error('subirDniAlServidor: fallo en proxy', [
                        'codi_pers' => $codiPers,
                        'lado'      => $lado,
                        'status'    => $response->status(),
                        'body'      => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                $fallos[] = $lado;
                Log::error('subirDniAlServidor: excepcion', [
                    'codi_pers' => $codiPers,
                    'lado'      => $lado,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return ['ok' => empty($fallos), 'faltan' => $fallos];
    }

    /**
     * Consulta al servidor de archivos qué caras del DNI del personal ya existen.
     * Devuelve ['anverso' => bool, 'reverso' => bool, 'consultado' => bool].
     * 'consultado' = false cuando el servidor de archivos no respondió; en ese caso
     * NO se debe bloquear el guardado.
     */
    private function dniPersonalEnServidor(string $codiPers): array
    {
        $codiPers  = trim($codiPers);
        $nombre    = $codiPers . '.jpg';
        $resultado = ['anverso' => false, 'reverso' => false, 'consultado' => false];

        if ($codiPers === '') {
            return $resultado;
        }

        $consultado = true;
        foreach (['anverso' => 'DNI1_1', 'reverso' => 'DNI2_1'] as $lado => $ruta) {
            try {
                $resultado[$lado] = Http::timeout(5)
                    ->head("http://190.116.178.163/Biblioteca_Grafica/{$ruta}/{$nombre}")
                    ->successful();
            } catch (\Throwable $e) {
                $resultado[$lado] = false;
                $consultado       = false;
            }
        }

        $resultado['consultado'] = $consultado;

        return $resultado;
    }

    /**
     * Indica qué caras del DNI del personal ya están en el servidor de archivos.
     * GET /api/dj/get-dni-personal?codi_pers=XXXXX
     */
    public function getDniPersonal(Request $request)
    {
        $codiPers = trim((string) $request->get('codi_pers', ''));
        if ($codiPers === '') {
            return response()->json(['success' => false, 'message' => 'codi_pers es obligatorio.'], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->dniPersonalEnServidor($codiPers),
        ]);
    }

    /**
     * Indica si la foto del personal ya existe en el servidor de archivos.
     * Devuelve true (existe), false (no existe) o null (no se pudo consultar:
     * en ese caso NO se debe bloquear el guardado).
     */
    private function fotoPersonalEnServidor(string $codiPers): ?bool
    {
        $codiPers = trim($codiPers);
        if ($codiPers === '') {
            return null;
        }

        try {
            return Http::timeout(5)
                ->head("http://190.116.178.163/Biblioteca_Grafica/Fotos/{$codiPers}.jpg")
                ->successful();
        } catch (\Throwable $e) {
            Log::warning('fotoPersonalEnServidor: sin respuesta del servidor de archivos', [
                'codi_pers' => $codiPers,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sube la foto del personal al servidor de archivos.
     * No lanza excepción: devuelve ['ok' => bool, 'faltan' => [lados fallidos]].
     */
    private function subirFotoAlServidor(?string $codiPers, $foto): array
    {
        $codiPers = trim((string) $codiPers);
        if (!$foto) {
            return ['ok' => true, 'faltan' => []];
        }
        if ($codiPers === '') {
            return ['ok' => false, 'faltan' => ['foto']];
        }

        // Nombre fijo: CODI_PERS.jpg
        $nameFile = $codiPers . '.jpg';

        try {
            $response = Http::withToken('457862h45hj7u5126h58d2s51s2s')
                ->attach('archivo', file_get_contents($foto->getRealPath()), $nameFile)
                ->post('http://190.116.178.163/apps/api/file-control/charge_file.php', [
                    'nameFile' => $nameFile,
                    'ruta'     => 'Fotos',
                ]);

            $proxyData = $response->json();
            if ($response->failed() || (isset($proxyData['success']) && $proxyData['success'] === false)) {
                Log::error('subirFotoAlServidor: fallo en proxy', [
                    'codi_pers' => $codiPers,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);

                return ['ok' => false, 'faltan' => ['foto']];
            }
        } catch (\Throwable $e) {
            Log::error('subirFotoAlServidor: excepcion', [
                'codi_pers' => $codiPers,
                'error'     => $e->getMessage(),
            ]);

            return ['ok' => false, 'faltan' => ['foto']];
        }

        return ['ok' => true, 'faltan' => []];
    }

    /**
     * Indica si el personal ya tiene foto en el servidor de archivos.
     * GET /api/dj/get-foto-personal?codi_pers=XXXXX
     */
    public function getFotoPersonal(Request $request)
    {
        $codiPers = trim((string) $request->get('codi_pers', ''));
        if ($codiPers === '') {
            return response()->json(['success' => false, 'message' => 'codi_pers es obligatorio.'], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => ['existe' => $this->fotoPersonalEnServidor($codiPers)],
        ]);
    }

    private function parsearFechaNacimiento(string $fecha): ?\Carbon\Carbon
    {
        $fecha = trim($fecha);

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $partes)) {
            $fecha = sprintf('%04d-%02d-%02d', $partes[3], $partes[2], $partes[1]);
        } elseif (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha)) {
            return null;
        }

        try {
            $fechaCarbon = \Carbon\Carbon::createFromFormat('!Y-m-d', $fecha);
            return $fechaCarbon
                && $fechaCarbon->isValid()
                && $fechaCarbon->format('Y-m-d') === $fecha
                ? $fechaCarbon->startOfDay()
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function obtenerReglasEdad(): array
    {
        $reglas = DB::select(
            "WITH reglas_ordenadas AS (
                SELECT
                    CODI_VALO_UNIT,
                    TRY_CONVERT(INT, VALOR) AS VALOR,
                    ROW_NUMBER() OVER (
                        PARTITION BY CODI_VALO_UNIT
                        ORDER BY
                            CASE
                                WHEN FECH_ASIG <= GETDATE()
                                    AND (FECH_ASIG_FIN IS NULL OR FECH_ASIG_FIN >= GETDATE())
                                THEN 0
                                ELSE 1
                            END,
                            FECH_ASIG DESC
                    ) AS posicion
                FROM si_solm.dbo.VALORES_UNITARIOS_ANUALES
                WHERE CODI_VALO_UNIT IN (377, 176)
                  AND TRY_CONVERT(INT, ESTA_ACTI) = 1
                  AND TRY_CONVERT(INT, VALOR) IS NOT NULL
            )
            SELECT CODI_VALO_UNIT, VALOR
            FROM reglas_ordenadas
            WHERE posicion = 1"
        );

        $valores = [];
        foreach ($reglas as $regla) {
            $valores[(int) $regla->CODI_VALO_UNIT] = (int) $regla->VALOR;
        }

        if (!isset($valores[377], $valores[176])) {
            throw new \RuntimeException('No están configuradas las reglas vigentes de edad para registrar la DJ.');
        }

        return [
            'minima' => $valores[377],
            'maxima' => $valores[176],
        ];
    }

public function reporteAvancesDj(Request $request)
    {
        try {
            $sucursal = $request->get('sucursal', '00');
            $tipo = $request->get('tipo', '00');

            // Ejecutamos el SP que te pasó tu jefe
            $data = \DB::select(
                'EXEC sisolm_web.dbo.SW_REPORTE_LISTAR_AVANCE_DJ ?, ?',
                [$sucursal, $tipo]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en reporteAvancesDj: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function reporteEtapa4Dj(Request $request)
    {
        try {
            // Le metemos size enorme para traer todo y que Tabulator pagine en el Frontend
            $page = 1;
            $size = 100000; 
            $sucursal = $request->get('sucursal', '00');
            $sucursal = ($sucursal == '00') ? '' : $sucursal; 
            $search = ''; // El buscador ahora lo hará el frontend
            $usuario = session('usuario') ?? 'EMONTERO'; 

            $sql = "
                SET NOCOUNT ON;
                SET ARITHABORT ON;
                EXEC SW_LISTAR_PERSONAL_X_SUCURSAL_TOTAL_V3 
                    @codSucursal = ?, 
                    @page = ?, 
                    @size = ?, 
                    @search = ?, 
                    @tipo_per = ?, 
                    @vigencia = ?, 
                    @usuario = ?
            ";

            $data = \DB::select($sql, [
                $sucursal, 
                $page, 
                $size, 
                $search, 
                'TODOS', 
                'SI', 
                $usuario
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error("Error en reporteEtapa4Dj: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
