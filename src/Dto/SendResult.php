<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

use BetoCampoy\Champs\WhatsappSdk\Support\Jid;

/**
 * Envio ACEITO pelo gateway (HTTP 202) — ainda não é entrega. A entrega
 * chega depois por webhook `message.status`, casada por `waMessageId`
 * (contrato v1 §5 regras 2 e 4).
 */
final class SendResult
{
    public function __construct(
        public readonly string $waMessageId,
        public readonly ?Jid $jid,
        public readonly string $clientMessageId,
    ) {}
}
