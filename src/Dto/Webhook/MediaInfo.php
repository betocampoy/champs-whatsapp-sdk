<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

/** Metadados da mídia de uma mensagem (o arquivo em si é baixado à parte). */
final class MediaInfo
{
    public function __construct(
        public readonly ?string $mimetype,
        public readonly ?int $size,
        public readonly ?string $fileName,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mimetype: isset($data['mimetype']) ? (string) $data['mimetype'] : null,
            size: isset($data['size']) ? (int) $data['size'] : null,
            fileName: isset($data['fileName']) ? (string) $data['fileName'] : null,
        );
    }
}
