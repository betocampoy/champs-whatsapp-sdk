<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

/**
 * Metadados da mídia de uma mensagem. O gateway já baixou o arquivo antes do
 * webhook: `available` = buscar com `WhatsappGatewayInterface::getMedia()`
 * (dentro do prazo de retenção do gateway, padrão 24 h). Sem `available`,
 * `reason` diz por quê: `too_large` ou `download_failed`.
 */
final class MediaInfo
{
    public const REASON_TOO_LARGE = 'too_large';
    public const REASON_DOWNLOAD_FAILED = 'download_failed';

    public function __construct(
        public readonly ?string $mimetype,
        public readonly ?int $size,
        public readonly ?string $fileName,
        public readonly bool $available = false,
        public readonly ?string $reason = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mimetype: isset($data['mimetype']) ? (string) $data['mimetype'] : null,
            size: isset($data['size']) ? (int) $data['size'] : null,
            fileName: isset($data['fileName']) ? (string) $data['fileName'] : null,
            available: (bool) ($data['available'] ?? false),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
        );
    }
}
