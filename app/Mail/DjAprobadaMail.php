<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DjAprobadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $datosCorreo;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($datosCorreo)
    {
        $this->datosCorreo = $datosCorreo;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $asuntoDinamico = 'GRUPO SOLAR | Aprobación de información DJ';

        return $this->from(config('mail.from.address'), 'GRUPO SOLAR')
                    ->subject($asuntoDinamico)
                    ->bcc([
                        'webmater@gruposolmar.com.pe', // Mantén el correo tal cual lo pide la empresa
                        'proyectossw@gruposolmar.com.pe', 
                        'jhordantapiaespinoza@gmail.com'
                    ])
                    ->view('emails.dj_aprobada');
    }
}
