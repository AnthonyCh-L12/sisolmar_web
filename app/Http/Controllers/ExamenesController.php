<?php

namespace App\Http\Controllers;

use App\Models\ExamenesModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ExamenesController extends Controller
{
    public function obtenerCursosAV(): JsonResponse
    {
        $examenesModel = new ExamenesModel();

        $cursos = collect($examenesModel->obtenerCursosAV());

        return response()->json([
            'success' => true,
            'data'    => [
                'cursos' => $cursos,
                'total'  => $cursos->count(),
            ],
        ]);
    }

    public function obtenerDatosReporteAV(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dnis'   => ['required', 'array', 'min:1', 'max:1500'],
            'dnis.*' => ['required', 'string'],
            'quizId' => ['required', 'integer'],
        ]);

        try {
            $examenesModel = new ExamenesModel();
            $quizId        = (int) $validated['quizId'];

            // Normalizar: trim + minúsculas, sin vacíos ni duplicados
            $dnis = collect($validated['dnis'])
                ->map(fn($dni) => strtolower(trim($dni)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            // 2 queries en total, sin importar cuántos DNIs lleguen
            $detalles = $examenesModel->obtenerDetallesExamenesAV($dnis, $quizId);

            $attemptIds = collect($detalles)
                ->pluck('attempt_id')
                ->map(fn($id) => (int) $id)
                ->values()
                ->all();

            $preguntasPorIntento = $examenesModel->obtenerPreguntasYRespuestasLoteAV($attemptIds);

            $resultados = collect($dnis)->map(function ($dni) use ($detalles, $preguntasPorIntento) {
                $detalle = $detalles[$dni] ?? null;

                if (!$detalle) {
                    return [
                        'dni'     => $dni,
                        'success' => false,
                        'message' => 'El usuario no tiene intentos registrados en este examen.',
                        'data'    => null,
                    ];
                }

                $attemptId = (int) $detalle->attempt_id;

                return [
                    'success' => true,
                    'message' => 'Detalle del último intento obtenido correctamente.',
                    'data'    => [
                        'course_name'        => $detalle->course_name,
                        'attempt_id'         => $attemptId,
                        'attempt_date'       => $detalle->attempt_date,
                        'obtained_grade'     => $detalle->obtained_grade,
                        'attempt_number'     => (int) $detalle->attempt_number,
                        'start_time'         => $detalle->start_time,
                        'end_time'           => $detalle->end_time,
                        'duration'           => $detalle->duration,
                        'passing_percentage' => $detalle->passing_percentage,
                        'correct_answers'    => (int) $detalle->correct_answers,
                        'incorrect_answers'  => (int) $detalle->incorrect_answers,
                        'full_name'          => $detalle->full_name,
                        'dni'                => $dni,
                        'questions_answers'  => $preguntasPorIntento[$attemptId] ?? [],
                    ],
                ];
            })->values();

            $exitosos = $resultados->where('success', true)->count();

            return response()->json([
                'success' => true,
                'message' => 'Reporte procesado.',
                'data'    => [
                    'quiz_id'    => $quizId,
                    'total'      => $resultados->count(),
                    'exitosos'   => $exitosos,
                    'fallidos'   => $resultados->count() - $exitosos,
                    'resultados' => $resultados,
                ],
            ], 200);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al obtener el reporte del examen.',
                'data'    => null,
            ], 500);
        }
    }
}
