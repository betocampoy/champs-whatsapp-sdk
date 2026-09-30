<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

use BetoCampoy\Champs\WhatsappSdk\Enum\SessionStatus;

/** Estado de uma sessão no gateway (contrato v1 §3, `Session`). */
final class Session
{
    public function __construct(
        public readonly string $id,
        public readonly SessionStatus $status,
        public readonly ?SessionOwner $owner = null,
        public readonly bool $hasQr = false,
        public readonly ?string $lastError = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            status: SessionStatus::fromGateway(isset($data['status']) ? (string) $data['status'] : null),
            owner: is_array($data['me'] ?? null) ? SessionOwner::fromArray($data['me']) : null,
            hasQr: (bool) ($data['hasQr'] ?? false),
            lastError: isset($data['lastError']) ? (string) $data['lastError'] : null,
        );
    }
}
