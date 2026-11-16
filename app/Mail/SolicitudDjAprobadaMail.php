<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SolicitudDjAprobadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $datosCorreo;

    public function __construct(array $datosCorreo)
    {
        $this->datosCorreo = $datosCorreo;
    }

    public function build()
    {
        return $this->from(config('mail.mailers.sip.username'), 'GRUPO SOLAR')
            ->subject('GRUPO SOLAR | Solicitud de actualización de datos aprobada')
            ->view('emails.solicitud_dj_aprobada');
    }
}
