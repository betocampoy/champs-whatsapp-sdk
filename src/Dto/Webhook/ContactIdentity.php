<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

use BetoCampoy\Champs\WhatsappSdk\Support\Jid;

/**
 * Quem é o contato (o OUTRO lado da conversa), contrato v1 §4/§5 regra 1.
 *
 * `lid` e `phone` podem faltar, nunca os dois quando vem de mensagem. A
 * aplicação deve achar o contato por QUALQUER um dos dois e completar o que
 * faltar; nunca usar o telefone como única chave.
 */
final class ContactIdentity
{
    public function __construct(
        public readonly ?Jid $jid,
        public readonly ?Jid $lid,
        public readonly ?string $phone,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $lid = Jid::tryParse(isset($data['lid']) ? (string) $data['lid'] : null);
        $phone = isset($data['phone']) ? preg_replace('/\D+/', '', (string) $data['phone']) : null;

        return new self(
            jid: Jid::tryParse(isset($data['jid']) ? (string) $data['jid'] : null),
            lid: $lid !== null && $lid->isLid() ? Jid::parse($lid->bare()) : null,
            phone: $phone === '' ? null : $phone,
        );
    }

    public function hasAny(): bool
    {
        return $this->lid !== null || $this->phone !== null;
    }
}
