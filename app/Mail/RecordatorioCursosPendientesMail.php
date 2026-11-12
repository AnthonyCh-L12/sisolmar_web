<?php

  namespace App\Mail;

  use Illuminate\Bus\Queueable;
  use Illuminate\Mail\Mailable;
  use Illuminate\Mail\Mailables\Content;
  use Illuminate\Mail\Mailables\Envelope;
  use Illuminate\Queue\SerializesModels;

  class RecordatorioCursosPendientesMail extends Mailable
  {
      use Queueable, SerializesModels;

      public function __construct(
          public readonly object $usuario,
          public readonly array  $cursos,
      ) {
          $this->onQueue('emails');
      }

      public function envelope(): Envelope
      {
          $total = count($this->cursos);

          return new Envelope(
              subject: "Tienes {$total} cursos pendientes por iniciar",
          );
      }

      public function content(): Content
      {
          $cursosPendientes = array_map(fn($curso) => [
              'nombre' => $curso->course_name,
          ], $this->cursos);

          return new Content(
              view: 'emails.recordatorio-pendientes',
              with: [
                  'full_name'          => $this->usuario->full_name,
                  'cursos_pendientes'  => $cursosPendientes,
              ],
          );
      }

      public function attachments(): array
      {
          return [];
      }
  }