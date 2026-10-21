<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

use BetoCampoy\Champs\WhatsappSdk\Support\Jid;

/**
 * Grupo de uma mensagem (`Message.group`, contrato v1 §4). Só vem quando a
 * sessão tem `monitorGroups`; aí o chat é o grupo e `IncomingMessage::$contact`
 * é quem mandou.
 */
final class GroupRef
{
    public function __construct(
        public readonly Jid $jid,
        /** Nome do grupo no WhatsApp; null quando o gateway não conseguiu os metadados. */
        public readonly ?string $subject = null,
    ) {
        if (!$jid->isGroup()) {
            throw new \InvalidArgumentException(sprintf('JID de grupo inválido: "%s".', $jid));
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $jid = Jid::tryParse(isset($data['jid']) ? (string) $data['jid'] : null);
        if ($jid === null) {
            throw new \InvalidArgumentException('Grupo sem jid.');
        }
        $subject = isset($data['subject']) ? trim((string) $data['subject']) : '';

        return new self($jid, $subject === '' ? null : $subject);
    }
}
