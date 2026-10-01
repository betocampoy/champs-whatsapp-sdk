<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

/**
 * Mensagem a citar no envio (sai como "resposta" a ela, como no celular).
 * O gateway não guarda histórico: a aplicação informa o id, quem enviou e
 * um trecho do texto (aparece no balão da citação).
 */
final class QuotedMessage
{
    public function __construct(
        public readonly string $waMessageId,
        public readonly bool $fromMe,
        public readonly ?string $text = null,
    ) {
        if (!preg_match('/^[A-Za-z0-9]{1,64}$/', $waMessageId)) {
            throw new \InvalidArgumentException(sprintf('waMessageId inválido: "%s".', $waMessageId));
        }
    }

    /** @return array{waMessageId: string, fromMe: bool, text: string} */
    public function toArray(): array
    {
        return ['waMessageId' => $this->waMessageId, 'fromMe' => $this->fromMe, 'text' => mb_substr((string) $this->text, 0, 300)];
    }
}
