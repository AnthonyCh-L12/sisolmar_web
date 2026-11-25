<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FileControl extends Model
{
    use HasFactory;

    public static function getPersonal()
    {
        $sucursal = 0;
        return DB::select('EXEC SW_LISTAR_PERSONAL_X_SUCURSAL ?', [$sucursal]);
    }

    public static function getPersonalLegajosPdf($codSucursal = '0', $vigencia = 'SI', $cliente = 'T', $cargo = 'T')
    {
        return DB::select('EXEC SW_LISTAR_PERSONAL_X_SUCURSAL_LEGAJO_PDF ?, ?, ?, ?, ?', [
            $codSucursal,
            '01',         // @empresa
            $vigencia,    // @vigencia
            $cliente,     // @cliente
            $cargo        // @cargo
        ]);
    }

    public static function getListaDJ()
    {
        return DB::select('EXEC [dbo].[SW_LISTAR_PERSONAL_DJ]');
    }

    public static function getDocsXPersona($codPersonal = '1', $usuario = '0')
    {
        return DB::select('EXEC dbo.folios_por_personal_jhordan ?, ?', [$codPersonal, $usuario]);
    }

    public static function getClientes()
    {
        return DB::select('EXEC SW_LISTAR_CLIENTES');
    }

    

    public static function getClientesLegajos()
    {
        return DB::select('EXEC SW_LISTAR_CLIENTES_LEGAJOS');
    }

    public static function getCargosXCliente($cliente)
    {
        return DB::select('EXEC SW_LISTAR_CARGOS_X_CLIENTES_LEGAJOS ?', [$cliente]);
    }

    public static function getSucursales()
    {
        $usuario  = session('usuario');
        return DB::select('EXEC SW_LISTAR_SUCURSALES ?', [$usuario]);
    }

    public static function getCargos()
    {
        return DB::select('EXEC SW_LISTAR_CARGO_GESTION_DE_CARGOS');
    }

    public static function getLegajos($cliente, $cargo, $codPersonal)
    {
        return DB::select('EXEC SW_LISTAR_LEGAJOS_X_PERSONA ?, ?, ?', [$cliente, $cargo, $codPersonal]);
    }

    public static function getFolios()
    {
        return DB::select('EXEC SW_LISTAR_FOLIOS');
    }

    public static function getAllFolios()
    {
        return DB::select('EXEC SW_LISTAR_FOLIOS');
    }

    /*public static function saveCargo(Request $request){
        DB::table('sw_cargos')->insert([
            'descripcion' => $request->nombre,
            'creado_por' => 'SOPORTE_SW',
            'fecha_creacion' => now(),
        ]);

        return response()->json(['mensaje' => 'Producto agregado con éxito'], 201);
    }*/

    public static function saveSolicitud($codigo, $tiene, $cargo, $cliente, $creado_por){
        return DB::statement('EXEC SW_SAVE_SOLICITUD ?, ?, ?, ?, ?', [$codigo, $cliente, $cargo, $tiene, $creado_por]);
    }

    public static function getPeriodos()
    {
        return DB::select('EXEC SW_LISTAR_PERIODOS');
    }

    public static function getCoincidencias($cliente, $cargo)
    {
        return DB::select('EXEC SW_LISTAR_COINCIDENCIAS_LEGAJOS ?, ?', [$cliente, $cargo]);
    }
    public static function getFoliosClienteCargo($cliente, $cargo)
    {
        return DB::select('EXEC SW_LISTAR_LEGAJOS_X_CLIENTE_X_CARGO ?, ?', [$cliente, $cargo]);
    }

    //GUARDAR DATOS DE LOS FOLIOS
    public static function saveFolioPersonal($fecha_emision, $fecha_caducidad, $codFolio, $codPersonal/*, $filePath*/)
    {
        // Insertar directamente en la tabla 'folios' usando el Query Builder
        $inserted = DB::table('sw_folios_detalles')->insert([
            'fecha_emision' => $fecha_emision,
            'fecha_caducidad' => $fecha_caducidad,
            'codFolio' => $codFolio,
            'codPersonal' => $codPersonal,
            //'ruta_archivo' => $filePath
        ]);

        return $inserted;
    }

    // GUARDAR DJ DE PERSONA (codFolio=25, sin caducidad, solo PDF)
    public static function saveDjFolioPersonal($fecha_emision, $codPersonal, $ruta_archivo)
    {
        return DB::table('sw_folios_detalles')->insert([
            'codFolio'        => 25,
            'codPersonal'     => $codPersonal,
            'fecha_emision'   => date('Y-m-d', strtotime($fecha_emision)),
            'fecha_caducidad' => null,
            'ruta_archivo'    => $ruta_archivo,
            'creado_por'      => null,
            'habilitado'      => 1,
            'migra'           => null,
            'fecha_creacion'  => DB::raw('GETDATE()'),
        ]);
    }

    public static function saveDjFolioPersonalAux($fecha_emision, $codPersonal, $ruta_archivo, $usuario)
    {
        return DB::table('sw_folios_detalles')->insert([
            'codFolio'        => 25,
            'codPersonal'     => $codPersonal,
            'fecha_emision'   => date('Y-m-d', strtotime($fecha_emision)),
            'fecha_caducidad' => null,
            'ruta_archivo'    => $ruta_archivo,
            'creado_por'      => $usuario,
            'habilitado'      => 1,
            'migra'           => null,
            'fecha_creacion'  => DB::raw('GETDATE()'),
        ]);
    }
     public static function saveFolio($nombre, $tipo, $obligatorio, $vencimiento, $tipo_fecha, $plataforma, $responsable = null, $usuario = null, $cod_categoria = null, $solo_lectura = 0)
    {
        $inserted = DB::table('sw_folios')->insert([
            'nombre'          => $nombre,
            'tipo'            => $tipo,
            'obligatorio'     => $obligatorio,
            'vencimiento'     => $vencimiento,
            'tipo_fecha'      => $tipo_fecha,
            'plataforma'      => $plataforma,
            'codResponsable'  => $responsable,
            'cod_categoria'   => $cod_categoria,
            'fecha_creacion' => DB::raw('GETDATE()'),
            'solo_lectura' => $solo_lectura,
            'creado_por' => $usuario
        ]);

        return $inserted;
    }
     public static function updateFolio($codigo, $nombre, $tipo, $obligatorio, $vencimiento, $tipo_fecha, $plataforma, $responsable = null, $usuario = null, $cod_categoria = null, $solo_lectura = 0)
      {
          $updated = DB::table('sw_folios')
              ->where('codigo', $codigo)
              ->update([
                  'nombre'             => $nombre,
                  'tipo'               => $tipo,
                  'obligatorio'        => $obligatorio,
                  'vencimiento'        => $vencimiento,
                  'tipo_fecha'         => $tipo_fecha,
                  'plataforma'         => $plataforma,
                  'codResponsable'     => $responsable,
                  'cod_categoria'      => $cod_categoria,
                  'solo_lectura' => $solo_lectura,
                  'fecha_modificacion' => DB::raw('GETDATE()'),
                  'modificado_por' => $usuario
              ]);

          return $updated;
      }
    public static function disabledFolio($codigo)
    {
        $updated = DB::table('sw_folios')
            ->where('codigo', $codigo)
            ->update([
                'habilitado' => 0,
                'fecha_modificacion' => DB::raw('GETDATE()'),
            ]);

        if ($updated) {
            DB::table('sw_folio_encargado')
                ->where('cod_folio', $codigo)
                ->update(['habilitado' => 0]);
        }

        return $updated;
    }

    public static function activarFolio($codigo)
    {
        $updated = DB::table('sw_folios')
            ->where('codigo', $codigo)
            ->update([
                'habilitado' => 1,
                'fecha_modificacion' => DB::raw('GETDATE()'),
            ]);

        if ($updated) {
            DB::table('sw_folio_encargado')
                ->where('cod_folio', $codigo)
                ->update(['habilitado' => 1]);
        }

        return $updated;
    }


    public static function saveCargo($descripcion, $nombre, $abreviatura, $cod_servicio, $cod_subservicio, $cod_tipo, $cod_area, $usuario){
        $inserted = DB::table('sw_cargos')->insert([
            'descripcion' => $descripcion,
            'nombre' => $nombre,
            'abreviatura' => $abreviatura,
            'cod_servicio' => $cod_servicio,
            'cod_subservicio' => $cod_subservicio,
            'cod_tipo' => $cod_tipo,
            'cod_area' => $cod_area,
            'creado_por' => $usuario
        ]);
        return $inserted;
    }

     public static function existeNombreFolio($nombre, $excluir = null)
      {
          $query = DB::table('sw_folios')
              ->whereRaw('UPPER(nombre) = UPPER(?)', [$nombre])
              ->where('habilitado', 1);

          if ($excluir) {
              $query->where('codigo', '!=', $excluir);
          }

          return $query->exists();
      }

     public static function existeNombreCargo($nombre, $excluir = null)
      {
          $query = DB::table('sw_cargos')
              ->whereRaw('UPPER(nombre) = UPPER(?)', [$nombre])
              ->where('habilitado', 1);

          if ($excluir) {
              $query->where('codigo', '!=', $excluir);
          }

          return $query->exists();
      }

    // public static function validarLegajo($folio, $codCliente, $codCargo, $codLegajo){
    //     return DB::select('SELECT TOP 1 * FROM sw_legajos_detalles WHERE codFolio = ? AND codCliente = ? AND codCargo = ? AND habilitado = 1 AND codLegajo = ?', [$folio, $codCliente, $codCargo, $codLegajo]);
    // }

    public static function validarLegajo($folio, $codCliente, $codCargo, $codLegajo = null) {
        return DB::table('sw_legajos_detalles')
            ->where('codFolio', $folio)
            ->where('codCliente', $codCliente)
            ->where('codCargo', $codCargo)
            ->where('codLegajo', $codLegajo)
            //->where('habilitado', 1)
            ->first(); // Esto ya hace TOP 1 en SQL Server
    }


    public static function QuitarTodosLegajos($codLegajo, $usuario)
    {
        // 1. Obtener los codFolio de los registros que serán actualizados
        $folios = DB::table('sw_legajos_detalles')
            ->where('codLegajo', $codLegajo)
            ->where('habilitado', 1) // solo los habilitados, si aplica
            ->pluck('codFolio')
            ->toArray(); // opcional, para tener un array plano

        // 2. Si no hay folios, evitar actualizar
        if (empty($folios)) {
            return [];
        }

        // 3. Ejecutar la actualización
        DB::table('sw_legajos_detalles')
            ->where('codLegajo', $codLegajo)
            ->whereIn('codFolio', $folios)
            ->update([
                'habilitado' => 0,
                //'modificado_por' => $usuario,
                'fecha_modificacion' => DB::raw('GETDATE()')
            ]);

        // 4. Retornar los folios que fueron afectados
        return $folios;
    }

    // public static function saveLegajoMain($nombre){
    //     return DB::select('EXEC SW_INSERTAR_LEGAJO_MAIN ?', [$nombre]);
    // }

    public static function saveLegajoMain($nombre){
        if(empty(trim($nombre))) {
            throw new \InvalidArgumentException('El nombre no puede estar vacío.');
        }
        return DB::table('sw_legajos')->insertGetId([
            'descripcion' => $nombre,
            'habilitado' => 1,
            'creado_por' => session('usuario'),
            'fecha_creacion' => DB::raw('GETDATE()'),
        ]);
    }




    // public static function saveLegajo($folio, $codCliente, $codCargo, $codLegajo){
    //     return DB::select('EXEC SW_INSERTAR_LEGAJO_DETALLE ?, ?, ?, ?', [$codCliente, $codCargo, $codLegajo, $folio]);
    // }

    public static function saveLegajo($folio, $codCliente, $codCargo, $codLegajo, $usuario){
        return DB::table('sw_legajos_detalles')->insert([
            'codCliente' => $codCliente,
            'codCargo'   => $codCargo,
            'codLegajo'  => $codLegajo,
            'codFolio'   => $folio,
            'habilitado' => 1,
            'creado_por' => $usuario,
            'fecha_creacion' => DB::raw('GETDATE()')
        ]);
    }

    public static function updateLegajo($folio, $codCliente, $codCargo, $codLegajo, $usuario){
       return DB::table('sw_legajos_detalles')
        ->where('codCliente', $codCliente)
        ->where('codCargo', $codCargo)
        ->where('codLegajo', $codLegajo)
        ->where('codFolio', $folio)
        ->update([
            'habilitado' => 1,
            'modificado_por' => $usuario,
            'fecha_modificacion' => DB::raw('GETDATE()')
        ]);
    }

    public static function actualizarNotificacion($folio, $codCliente, $codCargo){
        return DB::table('sw_solicitud_cargo_comercial')
            ->where('codCliente', $codCliente)
            ->where('codCargo', $codCargo)
            ->where('codFolio', $folio)
            ->where('habilitado', 1)
            ->where('tipoSolicitar', 0)
            //->where('fecha_creacion', '>=', DB::raw('DATEADD(HOUR, -24, GETDATE())'))
            ->update([
                'listo' => 1,
                'fecha_modificacion' => DB::raw('GETDATE()')
            ]);
    }

    public static function actualizarNotificacionDes($folio, $codCliente, $codCargo){
        $existe = DB::table('sw_solicitud_cargo_comercial')
            ->where('codCliente', $codCliente)
            ->where('codCargo', $codCargo)
            ->where('codFolio', $folio)
            ->where('habilitado', 1)
            ->where('tipoSolicitar', 1)
            ->where('fecha_creacion', '>=', DB::raw('DATEADD(HOUR, -24, GETDATE())'))
            ->exists();

        if (!$existe) {
            return false;
        }

        return DB::table('sw_solicitud_cargo_comercial')
            ->where('codCliente', $codCliente)
            ->where('codCargo', $codCargo)
            ->where('codFolio', $folio)
            ->where('habilitado', 1)
            ->where('tipoSolicitar', 1)
            ->where('fecha_creacion', '>=', DB::raw('DATEADD(HOUR, -24, GETDATE())'))
            ->update([
                'listo' => 1,
                'fecha_modificacion' => DB::raw('GETDATE()')
            ]);
    }


    public static function listarNotificaciones(){
        return DB::select('EXEC SW_LISTAR_NOTIFICACIONES');
    }

    public static function getFoliosXLegajo($codCliente, $codCargo){
        return DB::select('EXEC SW_LISTAR_FOLIOS_X_LEGAJO ?, ?' , [$codCliente, $codCargo]);
    }

    public static function getAreas(){
        return DB::select('EXEC SW_LISTAR_AREAS ');
    }

    public static function getPosicion(){
        return DB::select('EXEC SW_LISTAR_POSICION ');
    }

    public static function getGrupo(){
        return DB::select('EXEC SW_LISTAR_GRUPO ');
    }

    public static function getGrupoId($codigo){
        return DB::select('EXEC SW_LISTAR_GRUPO_ID ? ', [$codigo]);
    }

    public static function insertarCargo($data){
        return DB::statement('EXEC SW_INSERTAR_CARGO ?, ?, ?, ?, ?, ?, ?, ?', $data);
    }

    public static function activarCargo($codigo, $data)
    {
        return DB::table('sw_cargos')
            ->where('codigo', $codigo)
            ->update(['habilitado' => $data]);
    }

    public static function updateCargo($codigo, $cod_tipo, $cod_area, $nombre, $descripcion, $abreviatura, $cod_servicio, $cod_subservicio, $usuario = null)
    {
        return DB::table('sw_cargos')
            ->where('codigo', $codigo)
            ->update([
                'cod_tipo'           => $cod_tipo,
                'cod_area'           => $cod_area,
                'nombre'             => $nombre,
                'descripcion'        => $descripcion,
                'abreviatura'        => $abreviatura,
                'cod_servicio'       => $cod_servicio,
                'cod_subservicio'    => $cod_subservicio,
                'modificado_por'     => $usuario,
                'fecha_modificacion' => DB::raw('GETDATE()'),
            ]);
    }

      public static function getCargosXCodigo($codigo){
      $cargo = DB::table('sw_cargos')
          ->where('codigo', $codigo)
          ->select(
              'codigo',
              'nombre',
              'descripcion',
              'abreviatura',
              'cod_tipo',
              'cod_area',
              'habilitado',
              DB::raw('cod_servicio    AS cod_posicion'),
              DB::raw('cod_subservicio AS cod_grupo')
          )
          ->first();

      return $cargo ? [$cargo] : [];
  }

    public static function getRutaFolio($codFolio){
        if ((int) $codFolio === 65) {
            return ['CERTIFICADOS/CURRICULUM_VITAE'];
        }

        if ((int) $codFolio === 81) {
            $rutaVacuna = DB::table('si_solm.dbo.SISO_PARAMETRO')
                ->where('PARA_LEGAJO', 'LEG90')
                ->where('PARA_VIGENCIA', 'S')
                ->value('PARA_VALOR');

            return $rutaVacuna ? [trim($rutaVacuna)] : [];
        }

        $resultados = DB::select('EXEC SW_RUTA_ARCHIVO_FOLIO ?', [$codFolio]);

        if (!$resultados || count($resultados) === 0) {
            return false;
        }

        $rutas = array_map(function ($item) {
            return $item->ruta;
        }, $resultados);

        return $rutas;
    }

    public static function getViewPerDocs($codPersonal, $codFolio){
        $resultado = DB::select('EXEC SW_DOCS_PER_VIEW ?, ?', [$codPersonal, $codFolio]);
        return $resultado;
    }



    //BUSQUEDA DE LEGAJOS
    public static function getPersonalLegajos(){
        return DB::select('EXEC SW_LISTAR_COINCIDENCIAS_LEGAJOS_GENERAL');
    }

    /* ====================== PARA EL GENERADOR DE PDF ======================= */
    public static function getFoliosInfoPersona($codPersona, $codFolio){
        return DB::select('EXEC SW_LISTAR_DATOS_FOLIO_X_PERSONA ?, ?', [$codPersona, $codFolio]);
    }

    public static function listarPermisos($codigo){
        return DB::statement('EXEC SW_LISTAR_PERMISOS ?', [$codigo]);
    }

    public static function getFoliosXLegajo_comercial($codCliente, $codCargo){
        return DB::select('EXEC SW_LISTAR_FOLIOS_COMERCIAL ?, ?' , [$codCargo, $codCliente]);
    }

    public static function getSucursalXPersona($codPersona){
        $resultado = DB::select('EXEC SW_SUCURSAL_X_PERSONA ?', [$codPersona]);
        return $resultado ? $resultado[0]->sucursal : null;
    }

    public static function getReporteFiltro($codigo){
        return DB::select('EXEC SW_SISOLMAR_REPORTES ?', [$codigo]);
    }

    public static function getPersonalXId($codigo) {
        return DB::table('sw_MIGRA_PERSONAL')
            ->where('CODI_PERS', $codigo)
            ->first();
    }

    public static function getGradosInstruccion()
    {
        return DB::table('sw_edu_grado_intruccion')
            ->where('habilitado', 1)
            ->get();
    }

    public static function getGradosInstruccionDJ()
    {
        return DB::select(
                "SELECT NIED_CODIGO AS id, NIED_ABREVIADO AS text 
                FROM si_solm.dbo.SUNAT_NIVEL_EDUCATIVO 
                ORDER BY NIED_DESCRIPCION"
            );
    }

    public static function getCarreras()
    {
        return DB::table('sw_edu_carrera')
            ->where('habilitado', 1)
            ->get();
    }

     public static function getCarrerasDJ()
    {
        return DB::select(
                "SELECT CARR_CODIGO AS id, CARR_DESCRIPCION AS text, IEDU_CODIGO 
                FROM si_solm.dbo.SUNAT_CARRERAS 
                ORDER BY CARR_DESCRIPCION"
            );
    }

    public static function getInstituciones()
    {
        return DB::table('sw_edu_institucion')
            ->where('habilitado', 1)
            ->get();
    }

       public static function getInstitucionesDJ()
    {
        return DB::select(
                "SELECT IEDU_CODIGO AS id, IEDU_DESCRIPCION AS text 
                FROM si_solm.dbo.SUNAT_IEDUCATIVA 
                ORDER BY IEDU_DESCRIPCION"
            );
    }

    public static function getBancosDJ()
    {
        return DB::select(
            "SELECT CODI_BANC AS id, DESC_BANC AS text
            FROM si_solm.dbo.BANCOS
            WHERE CODI_BANC NOT IN ('07', '08')
            ORDER BY CODI_BANC"
        );
    }
    public static function getRoles($test = 0)
    {
        return $test !== 1 ? DB::table('sw_roles')
            ->where('habilitado', 1)
            ->where('test', 0)
            ->get() : DB::table('sw_roles')
            ->where('habilitado', 1)
            ->get();
    }
    public static function getCargosOperativos()
    {
        return DB::select("
            SELECT 
                CODI_CARG AS codigo, 
                DESC_CARGO AS nombre 
            FROM si_solm.dbo.CARGOS WITH (NOLOCK) 
            WHERE DESC_CARGO IS NOT NULL
            ORDER BY DESC_CARGO
        ");
    }

    public static function getCargosDj()
    {
        return DB::select("
            SELECT 
                CODI_CARG AS codigo, 
                DESC_CARGO AS nombre,
                CARGO_TIPO AS tipo
            FROM si_solm.dbo.CARGOS WITH (NOLOCK)
            WHERE CARG_VIGENCIA = 'SI' AND DESC_CARGO IS NOT NULL
            ORDER BY DESC_CARGO
        ");
    }
    // NUEVA FUNCIÓN: Traer clientes directamente del ERP
    public static function getClientesOperativos()
    {
        return DB::select("
            SELECT 
                CODI_CLIE_PROV AS codigo, 
                ABREVIATURA AS abreviatura 
            FROM si_solm.dbo.CLIENTE_PROVEEDOR WITH (NOLOCK)
            WHERE ESTA_CLIE_PROV = '1' -- Solo activos (opcional)
            ORDER BY ABREVIATURA
        ");
    }
    
    // NUEVA FUNCIÓN: Traer clientes directamente del ERP (A prueba de balas)
    public static function getClientesERP()
    {
        return DB::select("
            SELECT DISTINCT 
                CODI_CLIE_PROV AS codigo, 
                ABREVIATURA AS abreviatura 
            FROM si_solm.dbo.CLIENTE_PROVEEDOR WITH (NOLOCK)
            ORDER BY ABREVIATURA
        ");
    }

    
}
//hola
