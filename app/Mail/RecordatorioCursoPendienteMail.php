<?php

  namespace App\Mail;

  use Illuminate\Bus\Queueable;
  use Illuminate\Mail\Mailable;
  use Illuminate\Mail\Mailables\Content;
  use Illuminate\Mail\Mailables\Envelope;
  use Illuminate\Queue\SerializesModels;

  class RecordatorioCursoPendienteMail extends Mailable
  {
      use Queueable, SerializesModels;

      public function __construct(
          public readonly object $usuario,
          public readonly object $curso,
      ) {
          $this->onQueue('emails');
      }

      public function envelope(): Envelope
      {
          return new Envelope(
              subject: "Recordatorio: {$this->curso->course_name}",
          );
      }

      public function content(): Content
      {
          return new Content(
              view: 'emails.recordatorio-curso',
              with: [
                  'full_name'            => $this->usuario->full_name,
                  'course_name'          => $this->curso->course_name,
                  'course_shortname'     => $this->curso->course_shortname,
                  'enrolment_start_date' => $this->curso->enrolment_start_date,
                  'enrolment_end_date'   => $this->curso->enrolment_end_date,
              ],
          );
      }

      public function attachments(): array
      {
          return [];
      }
  }