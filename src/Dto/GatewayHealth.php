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
        /** Webhooks esperando entrega (null = gateway anterior ao campo). */
        public readonly ?int $pendingWebhooks = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            ok: (bool) ($data['ok'] ?? false),
            apiVersion: isset($data['apiVersion']) ? (string) $data['apiVersion'] : null,
            sessions: (int) ($data['sessions'] ?? 0),
            connected: (int) ($data['connected'] ?? 0),
            pendingWebhooks: isset($data['pendingWebhooks']) ? (int) $data['pendingWebhooks'] : null,
        );
    }
}
