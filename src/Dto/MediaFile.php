<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

/** Mídia recebida, baixada do gateway (`GET /v1/sessions/{id}/media/{waMessageId}`). */
final class MediaFile
{
    public function __construct(
        /** Conteúdo binário (dado pessoal: não logar). */
        public readonly string $bytes,
        public readonly string $mimetype,
        public readonly ?string $fileName = null,
    ) {}

    public function size(): int
    {
        return strlen($this->bytes);
    }
}
