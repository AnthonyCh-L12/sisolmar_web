<?php

namespace App\Http\Controllers;

use App\Mail\AlertaCaducidadMail;
use Illuminate\Http\Request;
use App\Models\NotificacionModel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class NotificacionController extends Controller{
    private function rolSinNotificacionesDj(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $nombreRol = DB::table('sw_roles')
            ->where('codigo', Auth::user()->tipo_rol)
            ->value('nombre');

        return mb_strtoupper(trim((string) $nombreRol))
            === mb_strtoupper(trim((string) config('report_access.role_without_dj_notifications')));
    }

    public function deleteNotificacion(Request $request){
        $codigo = $request->input('codigo');
        $habilitado = 0;

        $data = [
            "habilitado" => $habilitado,
            "listo" =>  $habilitado == 0 ? 1 : 0
        ];

        $update = NotificacionModel::updateNotificacion($codigo, $data);
       
        if ($update) {
            return response()->json(['success' => true, 'message' => 'Folio guardado correctamente']);
        } else {
            return response()->json(['success' => false, 'message' => 'Error al guardar el folio'], 500);
        }
    }

    // METODO NOTIFICACIONES
    public function foliosPorVencer()
    {
        if ($this->rolSinNotificacionesDj()) {
            return response()->json([]);
        }

        $dias = 10;

        $rows = NotificacionModel::foliosPorVencer($dias);

        $notificaciones = [];

        foreach ($rows as $row) {
            $cod = $row->codPersonal;

            if (!isset($notificaciones[$cod])) {
                $notificaciones[$cod] = [
                    'codPersonal' => $cod,
                    'personal'    => $row->personal,
                    'documentos'  => []
                ];
            }

            $notificaciones[$cod]['documentos'][] = [
                'folio'          => $row->documento,
                'fecha'          => $row->fecha_caducidad,
                'dias_restantes' => $row->dias_restantes,
            ];
        }

        return response()->json(array_values($notificaciones));
    }

    // CORREO
    public function enviarAlertasCaducidad()
    {
        $dias = 10;

        $rows = NotificacionModel::foliosPorVencer($dias);

        if (empty($rows)) {
            return response()->json([
                'message' => 'No hay documentos por vencer'
            ]);
        }

        $personas = [];

        foreach ($rows as $row) {

            $cod = $row->codPersonal;

            if (!isset($personas[$cod])) {
                $personas[$cod] = [
                    'codPersonal' => $cod,
                    'nombre'      => $row->personal,
                    'email'       => $row->email ?? null,
                    'documentos'  => []
                ];
            }

            $personas[$cod]['documentos'][] = [
                'nombre'          => $row->documento,
                'fecha_caducidad' => date('d/m/Y', strtotime($row->fecha_caducidad)),
                'dias_restantes'  => $row->dias_restantes,
            ];
        }

        foreach ($personas as $persona) {

            if (empty($persona['email'])) {
                continue;
            }

            Mail::to($persona['email'])
                ->send(new AlertaCaducidadMail([
                    'nombre_personal' => $persona['nombre'],
                    'nombre_empresa'  => 'SISOLMAR',
                    'documentos'      => $persona['documentos']
                ]));
        }

        return response()->json([
            'message' => 'Correos enviados correctamente',
            'total'   => count($personas)
        ]);
    }

    public function pendientesEtapa2()
    {
        if ($this->rolSinNotificacionesDj()) {
            return response()->json(['success' => true, 'total' => 0, 'data' => []]);
        }

        // Trae ['CALLAO' => 5, 'LIMA' => 12, ...]
        $conteosPorSucursal = NotificacionModel::pendientesEtapa2();
        
        // Suma todos los valores del array para el globo rojo de la campana
        $totalGeneral = array_sum($conteosPorSucursal);

        return response()->json([
            'success' => true,
            'total'   => $totalGeneral,
            'data'    => $conteosPorSucursal
        ]);
    }

    /** Avisos agrupados para personal con hijos registrados que cumplen 18 años. */
    public function hijosPorCumplirMayoriaEdad()
    {
        if ($this->rolSinNotificacionesDj()) {
            return response()->json(['success' => true, 'total' => 0, 'avisos' => ['30' => [], '15' => []]]);
        }

        $avisos = ['30' => [], '15' => []];
        foreach (NotificacionModel::hijosPorCumplirMayoriaEdad() as $row) {
            $dias = (string) $row->dias_aviso;
            if (isset($avisos[$dias])) {
                $avisos[$dias][] = [
                    'codigo_personal' => trim((string) $row->codigo_personal),
                    'personal' => trim((string) $row->personal),
                    'dni' => trim((string) ($row->dni ?? '')),
                    'sucursal' => trim((string) $row->sucursal),
                    'hijo' => trim((string) $row->hijo),
                    'fecha_nacimiento' => $row->fecha_nacimiento,
                    'fecha_mayoria' => $row->fecha_mayoria,
                    'dias_restantes' => (int) $row->dias_restantes,
                    'dias_aviso' => (int) $row->dias_aviso,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'total' => count($avisos['30']) + count($avisos['15']),
            'avisos' => $avisos,
        ]);
    }

public function getDemandasAdmin()
    {
        if ($this->rolSinNotificacionesDj()) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $demandas = NotificacionModel::obtenerSolicitudesDjPendientes();
        
        return response()->json([
            'success' => true,
            'data'    => $demandas
        ]);
    }

    /**
     * Atiende una notificación SIP desde la campanita.
     * La archiva (habilitado = 0); NO la borra, para conservar el historial.
     * Siempre responde success: si ya estaba archivada (doble clic) también
     * debe quitarse de la campanita.
     */
    public function deleteDemandaAdmin(Request $request)
    {
        $id = $request->input('id');
        $archivadas = NotificacionModel::archivarDemanda($id);

        return response()->json(['success' => true, 'archivadas' => $archivadas]);
    }

}




