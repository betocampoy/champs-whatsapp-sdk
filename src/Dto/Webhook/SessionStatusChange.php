<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

use BetoCampoy\Champs\WhatsappSdk\Dto\SessionOwner;
use BetoCampoy\Champs\WhatsappSdk\Enum\SessionStatus;

/** `session.status`: a sessão conectou, caiu ou foi deslogada pelo celular. */
final class SessionStatusChange
{
    public function __construct(
        public readonly SessionStatus $status,
        public readonly ?SessionOwner $owner,
        public readonly ?int $statusCode,
        public readonly ?string $error,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: SessionStatus::fromGateway(isset($data['status']) ? (string) $data['status'] : null),
            owner: is_array($data['me'] ?? null) ? SessionOwner::fromArray($data['me']) : null,
            statusCode: isset($data['statusCode']) ? (int) $data['statusCode'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }
}
