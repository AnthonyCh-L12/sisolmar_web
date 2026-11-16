<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExcepcionEdadMail extends Mailable
{
    use SerializesModels;

    public $datosCorreo;

    public function __construct(array $datosCorreo)
    {
        $this->datosCorreo = $datosCorreo;
    }

    public function build()
    {
        $asunto = 'GRUPO SOLAR | Excepción de edad - ' . $this->datosCorreo['nombre'];

        return $this->from(config('mail.from.address'), 'GRUPO SOLAR')
                    ->subject($asunto)
                    ->to([
                        ['email' => 'rrhh@solsecurity.pe', 'name' => 'RRHH'],
                    ])
                    ->cc([
                        //['email' => 'administracion@solsecurity.pe', 'name' => 'Administración'],
                        ['email' => 'legajosok@solsecurity.pe', 'name' => 'Legajos'],
                        ['email' => 'filecontrol@solsecurity.pe', 'name' => 'File Control'],
                        //['email' => 'giannanfaro@solsecurity.pe', 'name' => 'Gian Nanfaro'],
                        //['email' => 'pamelaherrera@solsecurity.pe', 'name' => 'Pamela Herrera'],
                        //['email' => 'madelinapaza@solsecurity.pe', 'name' => 'Madelin Apaza'],
                        ['email' => 'cesarsalazar@solsecurity.pe', 'name' => 'Cesar Salazar'],
                        ['email' => 'jhordantapiaespinoza@gmail.com', 'name' => 'Jhordan Tapia'],
                    ])
                    ->view('emails.excepcion_edad');
    }
}
