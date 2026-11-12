<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RecordatorioCursosPendientesMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecordatorioCursoPendienteMail;

class MailController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string',
            'template' => 'required|string',
            'data' => 'required|array'
        ]);

        $html = view(
            'emails.' . $request->template,
            $request->data
        )->render();

        Mail::html($html, function ($message) use ($request) {
            $message->to($request->to)
                ->subject($request->subject);
        });

        return response()->json([
            'success' => true,
            'message' => 'Correo enviado'
        ]);
    }

    public function enviarRecordatorioCurso(Request $request)
    {
        $email = trim((string) $request->input('email'));
        $full_name = trim((string) $request->input('full_name', ''));
        $course_id = (int) $request->input('course_id');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $course_id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos para enviar el recordatorio.',
            ], 422);
        }

        try {
            $curso = DB::connection('mysql_grupoihb')->select(
                'SELECT fullname AS course_name, shortname AS course_shortname, enddate AS enrolment_end_date, startdate AS enrolment_start_date
           FROM mdl_course WHERE id = ?',
                [$course_id]
            );

            if (empty($curso)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el curso solicitado.',
                ], 404);
            }

            $usuario = (object) [
                'email' => $email,
                'full_name' => $full_name,
            ];

            Mail::to($usuario->email)
                ->send(new RecordatorioCursoPendienteMail($usuario, $curso[0]));

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio con curso sin acceder enviado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error("Error enviando recordatorio individual a {$email}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el recordatorio sobre curso sin acceder',
            ], 500);
        }
    }

    public function enviarRecordatorioCursos(Request $request)
    {
        try {
            $dni = $request->dni;
            $nombreCompleto = $request->nombre_completo;
            $email = $request->email;

            $usuario = (object) [
                'email' => $email,
                'full_name' => $nombreCompleto
            ];

            $cursosSinAcceder = collect(DB::connection('mysql_grupoihb')->select('CALL SP_OBTENER_CURSOS_POR_USUARIO(?, ?)', [$dni, date('Y')]));

            $cursosLocalesHabilitados = DB::table('sw_cursos')
                ->where('habilitado', 1)
                ->whereNotNull('codigo_moodle')
                ->pluck('codigo_moodle')
                ->map(fn($c) => (int) $c)
                ->flip();

            $cursosSinAcceder = $cursosSinAcceder
                ->filter(fn($curso) => isset($cursosLocalesHabilitados[(int) $curso->course_id]))
                ->values()
                ->all();

            if (empty($cursosSinAcceder)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene cursos sin acceder.',
                ], 422);
            }

            Mail::to($usuario->email)->send(new RecordatorioCursosPendientesMail($usuario, $cursosSinAcceder));

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio con cursos sin acceder enviado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el recordatorio sobre cursos sin acceder',
            ], 500);
        }
    }
}
