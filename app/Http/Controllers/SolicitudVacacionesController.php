<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SolicitudVacacionesController extends Controller
{
       

public function index(Request $request)
{
    // Valores iniciales de los filtros
    $empresa = $request->input('empresa', 'T');
    $sucursal = $request->input('sucursal', 'T');
    $tipoCargo = $request->input('tipo_cargo', 'T');
    $personal = $request->input('personal', 'T');
    $mes = $request->input('mes', date('m'));
    $anio = $request->input('anio', date('Y'));

    // Cargar empresas
    $empresas = DB::select("
        SELECT EMPR_CODIGO, RAZON_SOCIAL
        FROM si_solm.dbo.Empresa WITH(NOLOCK)
        WHERE EMPR_VIGENCIA = 'SI'
        AND EMPR_CODIGO NOT IN ('03','04')
        ORDER BY RAZON_SOCIAL
    ");

    // Cargar sucursales
    $sucursales = DB::select("
        SELECT SUCU_CODIGO, SUCU_ABREVIATURA
        FROM si_solm.dbo.SISO_SUCURSAL WITH(NOLOCK)
        WHERE SUCU_VIGENCIA = 'SI'
        ORDER BY SUCU_ABREVIATURA
    ");

    $trabajadores = [];

    // Ejecutar solamente al presionar Buscar
    if ($request->input('buscar') == '1') {

        $request->validate([
            'empresa'    => 'required|string|max:2',
            'sucursal'   => 'required|string|max:2',
            'tipo_cargo' => 'required|string|max:2',
            'personal'   => 'required|string|max:5',
            'mes'        => 'required|integer|between:0,12',
            'anio'       => 'required|integer|min:0|max:9999',
        ]);

        // Si selecciona un trabajador, consultar todos sus periodos
        $anioConsulta = ($personal == 'T') ? $anio : 0;
        $mesConsulta = ($personal == 'T') ? $mes : 0;

        $trabajadores = DB::select(
            'EXEC si_solm.dbo.USP_LISTA_SOLICITUDES_VACACIONES_WEB ?, ?, ?, ?, ?, ?',
            [
                $empresa,
                $sucursal,
                $tipoCargo,
                $personal,
                $anioConsulta,
                $mesConsulta
            ]
        );
    }

    return view('rrhh.Solicitud', compact(
        'trabajadores',
        'empresas',
        'empresa',
        'sucursales',
        'sucursal',
        'tipoCargo',
        'personal',
        'mes',
        'anio'
    ));
}
public function getTiposCargoVacaciones()
{
    $cargos = DB::select("
        SELECT
            CODI_TIPO_CARG,
            DESC_TIPO_CARG
        FROM si_solm.dbo.TIPO_CARGO
        WHERE TIPO_TRAB = 'AD'
        ORDER BY DESC_TIPO_CARG
    ");

    return response()->json($cargos);
}

    public function getPersonalVacaciones(Request $request)
    {
    $empresa = $request->input('empresa', 'T');
    $sucursal = $request->input('sucursal', 'T');
    $tipoCargo = $request->input('tipo_cargo', 'T');

    $personal = DB::select("
        SELECT
            P.CODI_PERS,
            RTRIM(P.APEL_1) + ' ' + RTRIM(P.APEL_2) + ' ' +
            RTRIM(P.NOMB_1) + ' ' + ISNULL(P.NOMB_2, '') AS PERSONAL,
            G.CODI_CARG,
            TC.CODI_TIPO_CARG,
            TC.DESC_TIPO_CARG AS AREA,
            P.SUCU_CODIGO
        FROM si_solm.dbo.PERSONAL P WITH(NOLOCK)
        INNER JOIN si_solm.dbo.CARGOS G WITH(NOLOCK)
            ON P.CODI_CARG = G.CODI_CARG
        INNER JOIN si_solm.dbo.TIPO_CARGO TC WITH(NOLOCK)
            ON TC.CODI_TIPO_CARG = G.TIPO_CARG
        WHERE P.PERS_TIPOTRAB = '05'
          AND P.CODI_PERS NOT IN (
              '00836', '05793', '04931', '03688', '00966',
              '07165', '13208', '15658', '14419', '14215'
          )
          AND P.PERS_VIGENCIA = 'SI'
          AND (P.EMPR_CODIGO = ? OR ? = 'T')
          AND (P.SUCU_CODIGO = ? OR ? = 'T')
          AND TC.TIPO_TRAB = 'AD'
          AND (? = 'T' OR TC.CODI_TIPO_CARG = ?)
        ORDER BY P.APEL_1, P.APEL_2, P.NOMB_1
    ", [
        $empresa, $empresa,
        $sucursal, $sucursal,
        $tipoCargo, $tipoCargo
    ]);

    return response()->json($personal);
    }
    public function detalle($codigo)
{
    // =========================================
    // CABECERA DE LA SOLICITUD
    // =========================================
    $cabecera = DB::selectOne("
        SELECT
            V.VACA_CODIGO,
            V.CODI_PERS,
            RTRIM(P.APEL_1) + ' ' +
            RTRIM(P.APEL_2) + ' ' +
            RTRIM(P.NOMB_1) + ' ' +
            ISNULL(P.NOMB_2, '') AS PERSONAL,

            V.FECH_INGRE_PLAN,
            V.VACA_FEC_INI,
            V.VACA_FEC_FIN,
            V.VACA_DIAS,
            V.TOTAL_DIAS,
            V.VACA_ANIO,
            V.VACA_MES,
            V.OBSERVACIONES,
            V.PLCA_CODIGO,

            V.VACA_FEC_INI AS PER_LAB_INI,
            V.VACA_FEC_FIN AS PER_LAB_FIN

        FROM si_solm.dbo.VACACIONES V WITH(NOLOCK)

        INNER JOIN si_solm.dbo.PERSONAL P WITH(NOLOCK)
            ON P.CODI_PERS = V.CODI_PERS

        WHERE V.VACA_CODIGO = ?
    ", [$codigo]);

    // Validar que exista la solicitud
    if (!$cabecera) {
        abort(404, 'Solicitud de vacaciones no encontrada.');
    }


    // =========================================
    // DETALLE DEL CRONOGRAMA
    // =========================================
    $detalle = DB::select("
        SELECT
            VC.VACR_CODIGO,
            VC.VACA_CODIGO,

            VC.VACR_AUTORIZA,

            RTRIM(PA.APEL_1) + ' ' +
            RTRIM(PA.APEL_2) + ' ' +
            RTRIM(PA.NOMB_1) AS AUTORIZANTE,

            VC.VACR_FEC_INI,
            VC.VACR_FEC_FIN,

            DATEDIFF(
                DAY,
                VC.VACR_FEC_INI,
                VC.VACR_FEC_FIN
            ) + 1 AS DIAS,

            si_solm.dbo.UF_CUENTA_DOMINGOS(
                VC.VACR_FEC_INI,
                VC.VACR_FEC_FIN
            ) AS DOMINGOS,

            VC.VACR_ESTADO,
            VC.VACR_VIGENCIA,

            VC.VACR_FEC_RETORNO,
            VC.FECHA_APROBADO

        FROM si_solm.dbo.VACACIONES_CRONOGRAMA VC WITH(NOLOCK)

        LEFT JOIN si_solm.dbo.PERSONAL PA WITH(NOLOCK)
            ON PA.CODI_PERS = VC.VACR_AUTORIZA

        WHERE VC.VACA_CODIGO = ?
          AND VC.VACR_VIGENCIA = 1

        ORDER BY VC.VACR_FEC_INI, VC.VACR_CODIGO
    ", [$codigo]);


    // =========================================
    // MOSTRAR LA VISTA
    // =========================================
    return view('rrhh.SolicitudDetalle', compact(
        'cabecera',
        'detalle'
    ));
    }
    public function iniciarVacaciones(Request $request, $codigo)
    {
        $request->validate([
            'cronogramas'   => 'required|array|min:1',
            'cronogramas.*' => 'required|integer'
        ]);

        $codigos = array_unique($request->input('cronogramas'));

        $resultado = DB::transaction(function () use ($codigo, $codigos) {

            // BLOQUEAR CABECERA
            $cabecera = DB::selectOne("
                SELECT TOTAL_DIAS
                FROM si_solm.dbo.VACACIONES WITH (UPDLOCK, ROWLOCK)
                WHERE VACA_CODIGO = ?
            ", [$codigo]);

            if (!$cabecera) {
                abort(404, 'Solicitud de vacaciones no encontrada.');
            }

            $saldoActual = (float)$cabecera->TOTAL_DIAS;
            $descuentoTotal = 0;
            $procesados = [];

            // VALIDAR CRONOGRAMAS SELECCIONADOS
            foreach ($codigos as $codiCronograma) {

                $fila = DB::selectOne("
                    SELECT
                        VACR_CODIGO,
                        VACR_ESTADO,

                        DATEDIFF(
                            DAY,
                            VACR_FEC_INI,
                            VACR_FEC_FIN
                        ) + 1 AS DIAS,

                        si_solm.dbo.UF_CUENTA_DOMINGOS(
                            VACR_FEC_INI,
                            VACR_FEC_FIN
                        ) AS DOMINGOS

                    FROM si_solm.dbo.VACACIONES_CRONOGRAMA
                        WITH (UPDLOCK, ROWLOCK)

                    WHERE VACA_CODIGO = ?
                    AND VACR_CODIGO = ?
                    AND VACR_VIGENCIA = 1
                ", [$codigo, $codiCronograma]);

                // No pertenece a la solicitud o no está vigente
                if (!$fila) {
                    continue;
                }

                // SOLO PROCESAR ESTADO 0
                if ((int)$fila->VACR_ESTADO !== 0) {
                    continue;
                }

                // DIAS MENOS DOMINGOS
                $diasDescuento = (int)$fila->DIAS - (int)$fila->DOMINGOS;

                if ($diasDescuento < 0) {
                    $diasDescuento = 0;
                }

                $descuentoTotal += $diasDescuento;

                $procesados[] = [
                    'codigo' => $fila->VACR_CODIGO,
                    'dias'   => $diasDescuento
                ];
            }

            // VALIDAR SALDO
            if ($descuentoTotal > $saldoActual) {
                return [
                    'error' => true,
                    'mensaje' => 'El descuento de ' . $descuentoTotal .
                        ' días supera el saldo disponible de ' . $saldoActual . ' días.'
                ];
            }

            // ACTUALIZAR CRONOGRAMAS
            foreach ($procesados as $fila) {

                DB::update("
                    UPDATE si_solm.dbo.VACACIONES_CRONOGRAMA
                    SET
                        VACR_ESTADO = 1,
                        FECHA_APROBADO = ISNULL(FECHA_APROBADO, GETDATE())
                    WHERE VACA_CODIGO = ?
                    AND VACR_CODIGO = ?
                    AND VACR_ESTADO = 0
                    AND VACR_VIGENCIA = 1
                ", [
                    $codigo,
                    $fila['codigo']
                ]);

            }

            // DESCONTAR TOTAL DE DIAS
            if ($descuentoTotal > 0) {

                DB::update("
                    UPDATE si_solm.dbo.VACACIONES
                    SET TOTAL_DIAS = TOTAL_DIAS - ?
                    WHERE VACA_CODIGO = ?
                ", [
                    $descuentoTotal,
                    $codigo
                ]);

            }

            return [
                'error' => false,
                'descuento' => $descuentoTotal,
                'cantidad' => count($procesados)
            ];

        });

        // SALDO INSUFICIENTE
        if ($resultado['error']) {

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $resultado['mensaje']);
        }

        // RESULTADO
        return redirect()
            ->back()
            ->withInput()
            ->with('success',
                'Se procesaron ' . $resultado['cantidad'] .
                ' cronogramas. Se descontaron ' .
                $resultado['descuento'] . ' días.'
            );
    }
    public function revertirVacaciones($codigo, $cronograma)
    {
        $resultado = DB::transaction(function () use ($codigo, $cronograma) {

            // BLOQUEAR CABECERA
            $cabecera = DB::selectOne("
                SELECT TOTAL_DIAS
                FROM si_solm.dbo.VACACIONES WITH (UPDLOCK, ROWLOCK)
                WHERE VACA_CODIGO = ?
            ", [$codigo]);

            if (!$cabecera) {
                abort(404, 'Solicitud de vacaciones no encontrada.');
            }

            // VALIDAR CRONOGRAMA
            $fila = DB::selectOne("
                SELECT
                    VACR_CODIGO,
                    VACR_ESTADO,

                    DATEDIFF(DAY, VACR_FEC_INI, VACR_FEC_FIN) + 1 AS DIAS,

                    si_solm.dbo.UF_CUENTA_DOMINGOS(
                        VACR_FEC_INI,
                        VACR_FEC_FIN
                    ) AS DOMINGOS

                FROM si_solm.dbo.VACACIONES_CRONOGRAMA
                    WITH (UPDLOCK, ROWLOCK)

                WHERE VACA_CODIGO = ?
                AND VACR_CODIGO = ?
                AND VACR_VIGENCIA = 1
            ", [$codigo, $cronograma]);

            if (!$fila || (int)$fila->VACR_ESTADO !== 1) {
                return [
                    'error' => true,
                    'mensaje' => 'El cronograma no está en actividad o ya fue revertido.'
                ];
            }

            // CALCULAR DIAS A DEVOLVER
            $diasDevolver = (int)$fila->DIAS - (int)$fila->DOMINGOS;

            if ($diasDevolver < 0) {
                $diasDevolver = 0;
            }

            // DEVOLVER EL CRONOGRAMA A EN ESPERA
            DB::update("
                UPDATE si_solm.dbo.VACACIONES_CRONOGRAMA
                SET
                    VACR_ESTADO = 0,
                    FECHA_APROBADO = NULL
                WHERE VACA_CODIGO = ?
                AND VACR_CODIGO = ?
                AND VACR_ESTADO = 1
                AND VACR_VIGENCIA = 1
            ", [$codigo, $cronograma]);

            // DEVOLVER LOS DIAS AL SALDO
            if ($diasDevolver > 0) {
                DB::update("
                    UPDATE si_solm.dbo.VACACIONES
                    SET TOTAL_DIAS = TOTAL_DIAS + ?
                    WHERE VACA_CODIGO = ?
                ", [$diasDevolver, $codigo]);
            }

            return [
                'error' => false,
                'dias' => $diasDevolver
            ];
        });

        if ($resultado['error']) {
            return redirect()->back()->with('error', $resultado['mensaje']);
        }

        return redirect()->back()->with('success',
            'Cronograma revertido correctamente. Se devolvieron ' .
            $resultado['dias'] . ' días al saldo disponible.'
        );
    }
}
