<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

/** Resposta de `GET /health` (contrato v1 §3). */
final class GatewayHealth
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $apiVersion,
        public readonly int $sessions,
        public readonly int $connected,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ok: (bool) ($data['ok'] ?? false),
            apiVersion: isset($data['apiVersion']) ? (string) $data['apiVersion'] : null,
            sessions: (int) ($data['sessions'] ?? 0),
            connected: (int) ($data['connected'] ?? 0),
        );
    }
}
