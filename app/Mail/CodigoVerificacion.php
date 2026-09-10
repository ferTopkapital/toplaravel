<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Código de verificación de un solo uso (manual §4.4).
 *
 * Se usa para los cuatro casos que exigen segundo factor: compromiso de
 * inversión, alta o cambio de cuenta destino, cambio de contraseña y consulta
 * de estados de cuenta. El motivo cambia el texto, no el mecanismo.
 */
class CodigoVerificacion extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $nombre,
        public string $codigo,
        public string $motivo,
        public int $vigenciaMinutos,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de verificación · Top Kapital',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.codigo-verificacion',
        );
    }
}
