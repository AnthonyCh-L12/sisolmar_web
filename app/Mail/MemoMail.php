<?php

  namespace App\Mail;

  use Barryvdh\DomPDF\Facade\Pdf;
  use Illuminate\Mail\Mailables\Attachment;
  use Illuminate\Mail\Mailable;
  use Illuminate\Mail\Mailables\Content;
  use Illuminate\Mail\Mailables\Envelope;
  use Illuminate\Bus\Queueable;
  use Illuminate\Queue\SerializesModels;
  use Illuminate\Support\Collection;

  class MemoMail extends Mailable
  {
      use Queueable, SerializesModels;

      public function __construct(
          public readonly string $nombreCompleto,
          public readonly string $cargoPersonal,
          public readonly array $cursosSinAcceder,
          public readonly int $tipoMemo,
          public readonly Collection $historicoMemos,
      ) {
          $this->onQueue('emails');
      }

      public function envelope(): Envelope
      {
          $mes = strtoupper(now()->locale('es')->translatedFormat('F'));
          $anio = now()->year;

          return new Envelope(
              subject: "Cursos AV {$mes} {$anio}: Medida disciplinaria por incumplir capacitaciones programadas: Sr. {$this->nombreCompleto}",
          );
      }

      public function content(): Content
      {
          $mes = strtoupper(now()->locale('es')->translatedFormat('F'));
          $anio = now()->year;

          $mensaje = $this->obtenerMensaje($mes, $anio);

          return new Content(
              view: 'emails.memorandum-body',
              with: [
                  'nombreCompleto'    => $this->nombreCompleto,
                  'cargoPersonal'     => $this->cargoPersonal,
                  'mensaje'           => $mensaje,
              ],
          );
      }

      private function obtenerMensaje($mes, $anio): string
      {
          switch ($this->tipoMemo) {
              case 1:
                  return "
                      <p>
                          Lamento comunicarle que el AREA DE CAPACITACION Y DESARROLLO de la empresa,
                          ha reportado que Ud. no ha cumplido con las Capacitaciones AV programadas
                          de {$mes} del {$anio}, por lo que de acuerdo con lo establecido en el
                          Reglamento Interno de Trabajo se ha procedido con la
                          <strong>MEDIDA DISCIPLINARIA</strong> correspondiente que le hacemos llegar
                          en el anexo a esta comunicación.
                      </p>

                      <p>
                          Simultáneamente en el SIP, encontrará el memorándum sancionador para que Ud.
                          firme la recepción correspondiente, siendo oportuno reiterarle que debe cumplir
                          con las capacitaciones pendientes en el plazo otorgado, a fin de no incurrir
                          en nueva falta disciplinaria.
                      </p>

                      <p>
                          Sin otro particular, quedamos atentos a la firma de recepción en el SIP
                          y a la ejecución de las capacitaciones pendientes.
                      </p>
                  ";

              case 2:
                  return "
                      <p>
                          El AREA DE CAPACITACION Y DESARROLLO de la empresa, ha reportado que Ud.
                          no ha cumplido con las Capacitaciones AV programadas de {$mes} del {$anio},
                          a pesar del plazo otorgado en una comunicación previa; en tal sentido,
                          de acuerdo con lo establecido en el Reglamento Interno de Trabajo se ha
                          procedido con una <strong>SEGUNDA MEDIDA DISCIPLINARIA</strong> que le hacemos
                          llegar en el anexo a esta comunicación.
                      </p>

                      <p>
                          Simultáneamente en el SIP, encontrará el segundo memorándum sancionador
                          para que Ud. firme la recepción correspondiente, siendo oportuno reiterarle
                          que deberá cumplir con las capacitaciones pendientes en el plazo otorgado,
                          a fin de no incurrir en nueva falta disciplinaria.
                      </p>

                      <p>
                          Sin otro particular, quedamos atentos a su confirmación.
                      </p>
                  ";

              case 3:
                  return "
                      <p>
                          Lamento comunicarle que el AREA DE CAPACITACION Y DESARROLLO de la empresa,
                          ha reportado que Ud. no ha cumplido con las Capacitaciones AV programadas
                          de {$mes} del {$anio} y que originaron
                          <strong>DOS MEDIDAS DISCIPLINARIAS</strong> previas, por lo que de acuerdo
                          con lo establecido en el Reglamento Interno de Trabajo, se ha procedido
                          con la medida disciplinaria correspondiente que le hacemos llegar en el
                          anexo a esta comunicación.
                      </p>

                      <p>
                          Simultáneamente en el SIP, encontrará el memorándum sancionador para que Ud.
                          firme la recepción correspondiente, siendo oportuno informarle que en caso
                          Ud. incumpla por tercera vez este requisito para el desempeño del cargo que ocupa,
                          su caso será pasado al área legal para evaluar su continuidad en la empresa.
                      </p>

                      <p>
                          Sin otro particular, quedamos atentos a su confirmación.
                      </p>
                  ";

              default:
                  return "";
          }
      }

      public function attachments(): array
      {
          $vistaPdf = match ($this->tipoMemo) {
              1 => 'emails.memorandum-1-cursos',
              2 => 'emails.memorandum-2-cursos',
              3 => 'emails.memorandum-3-cursos',
          };

          $primerMemo = $this->historicoMemos
              ->where('NUM_MEMO', 1)
              ->last();

          $segundoMemo = $this->historicoMemos
              ->where('NUM_MEMO', 2)
              ->last();

          $pdf = Pdf::loadView($vistaPdf, [
              'nombreCompleto'   => $this->nombreCompleto,
              'cargoPersonal'    => $this->cargoPersonal,
              'cursosSinAcceder' => $this->cursosSinAcceder,
              'fechaActual' => now()->format('d/m/Y'),

              'fechaPrimerMEMO' => $primerMemo
                  ? \Carbon\Carbon::parse($primerMemo->FECHA_ENVIO)
                  ->format('d/m/Y')
                  : null,

              'fechaSegundoMEMO' => $segundoMemo
                  ? \Carbon\Carbon::parse($segundoMemo->FECHA_ENVIO)
                  ->format('d/m/Y')
                  : null,
          ]);

          return [
              Attachment::fromData(
                  fn() => $pdf->output(),
                  "MEMORANDUM Nº0{$this->tipoMemo}-2026-RRHH.pdf"
              )->withMime('application/pdf'),
          ];
      }
  }