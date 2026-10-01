<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

/**
 * `Message` do contrato v1 §4: em `message.received` (o cliente mandou) e em
 * `message.sent_from_device` (`fromMe = true`: alguém respondeu pelo celular,
 * fora da aplicação). `contact` é sempre o outro lado.
 *
 * `text` é dado pessoal: nunca logar.
 */
final class IncomingMessage
{
    public function __construct(
        public readonly string $waMessageId,
        public readonly bool $fromMe,
        public readonly ContactIdentity $contact,
        public readonly ?string $pushName,
        /** Hora do WhatsApp (não a do processamento): use como "quando chegou". */
        public readonly \DateTimeImmutable $sentAt,
        public readonly string $messageType,
        public readonly ?string $text,
        public readonly bool $hasMedia,
        public readonly ?MediaInfo $media,
        /** Respondeu citando esta mensagem (id do WhatsApp), ou null. */
        public readonly ?string $quotedWaMessageId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $waMessageId = (string) ($data['waMessageId'] ?? '');
        if ($waMessageId === '') {
            throw new \InvalidArgumentException('Mensagem sem waMessageId.');
        }
        $contact = ContactIdentity::fromArray(is_array($data['contact'] ?? null) ? $data['contact'] : []);
        if (!$contact->hasAny()) {
            throw new \InvalidArgumentException('Mensagem sem lid nem telefone do contato.');
        }

        $timestamp = (int) ($data['timestamp'] ?? 0);

        return new self(
            waMessageId: $waMessageId,
            fromMe: (bool) ($data['fromMe'] ?? false),
            contact: $contact,
            pushName: isset($data['pushName']) && $data['pushName'] !== '' ? (string) $data['pushName'] : null,
            sentAt: $timestamp > 0 ? new \DateTimeImmutable('@' . $timestamp) : new \DateTimeImmutable(),
            messageType: (string) ($data['messageType'] ?? 'unknown'),
            text: isset($data['text']) ? (string) $data['text'] : null,
            hasMedia: (bool) ($data['hasMedia'] ?? false),
            media: is_array($data['media'] ?? null) ? MediaInfo::fromArray($data['media']) : null,
            quotedWaMessageId: isset($data['quotedWaMessageId']) && '' !== $data['quotedWaMessageId'] ? (string) $data['quotedWaMessageId'] : null,
        );
    }
}
