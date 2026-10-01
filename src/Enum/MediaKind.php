<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Enum;

/**
 * Tipo de anexo no envio (`type` do `SendMessage`, contrato v1). Define como
 * o WhatsApp mostra o arquivo: imagem e vídeo inline, áudio com player,
 * documento como arquivo para baixar.
 */
enum MediaKind: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';

    /** O jeito natural de mandar um arquivo deste mimetype (o resto vai como documento). */
    public static function fromMimetype(string $mimetype): self
    {
        $principal = strtolower(trim(explode(';', $mimetype)[0]));

        return match (true) {
            in_array($principal, ['image/jpeg', 'image/png', 'image/webp'], true) => self::IMAGE,
            in_array($principal, ['video/mp4', 'video/3gpp'], true) => self::VIDEO,
            str_starts_with($principal, 'audio/') => self::AUDIO,
            default => self::DOCUMENT,
        };
    }

    /** Legenda: o WhatsApp aceita em imagem, vídeo e documento; áudio não. */
    public function aceitaLegenda(): bool
    {
        return self::AUDIO !== $this;
    }
}
