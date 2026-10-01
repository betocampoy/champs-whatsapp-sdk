<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

use BetoCampoy\Champs\WhatsappSdk\Enum\AckStatus;
use BetoCampoy\Champs\WhatsappSdk\Support\Jid;

/**
 * Ack de uma mensagem enviada (`message.status`). Case por `waMessageId`,
 * nunca por `jid` (volta com o LID), e grave só se
 * `$atual->shouldReplace($update->status)` (contrato v1 §5 regras 2 e 3).
 */
final class MessageStatusUpdate
{
    public function __construct(
        public readonly string $waMessageId,
        public readonly ?Jid $jid,
        public readonly AckStatus $status,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $waMessageId = (string) ($data['waMessageId'] ?? '');
        $status = AckStatus::tryFrom((string) ($data['status'] ?? ''));
        if ($waMessageId === '' || $status === null) {
            throw new \InvalidArgumentException('message.status sem waMessageId ou com status desconhecido.');
        }

        return new self($waMessageId, Jid::tryParse(isset($data['jid']) ? (string) $data['jid'] : null), $status);
    }
}
