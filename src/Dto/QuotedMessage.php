<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

/**
 * Mensagem a citar no envio (sai como "resposta" a ela, como no celular).
 * O gateway não guarda histórico: a aplicação informa o id, quem enviou e
 * um trecho do texto (aparece no balão da citação).
 *
 * Em grupo, `$participant` é o JID de quem escreveu a citada (o `contact` da
 * mensagem recebida, ou o próprio número): sem ele a citação sai sem autor.
 */
final class QuotedMessage
{
    public function __construct(
        public readonly string $waMessageId,
        public readonly bool $fromMe,
        public readonly ?string $text = null,
        public readonly ?string $participant = null,
    ) {
        if (!preg_match('/^[A-Za-z0-9]{1,64}$/', $waMessageId)) {
            throw new \InvalidArgumentException(sprintf('waMessageId inválido: "%s".', $waMessageId));
        }
        if ($participant !== null && !preg_match('/^[^@\s]+@(lid|s\.whatsapp\.net)$/', $participant)) {
            throw new \InvalidArgumentException(sprintf('participant inválido: "%s".', $participant));
        }
    }

    /** @return array{waMessageId: string, fromMe: bool, text: string, participant?: string} */
    public function toArray(): array
    {
        $data = ['waMessageId' => $this->waMessageId, 'fromMe' => $this->fromMe, 'text' => mb_substr((string) $this->text, 0, 300)];
        if ($this->participant !== null) {
            $data['participant'] = $this->participant;
        }

        return $data;
    }
}
