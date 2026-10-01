<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Webhook;

use BetoCampoy\Champs\WhatsappSdk\Dto\Webhook\ContactIdentity;
use BetoCampoy\Champs\WhatsappSdk\Dto\Webhook\IncomingMessage;
use BetoCampoy\Champs\WhatsappSdk\Dto\Webhook\MessageStatusUpdate;
use BetoCampoy\Champs\WhatsappSdk\Dto\Webhook\SessionStatusChange;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\InvalidWebhookException;

/**
 * Envelope de webhook (contrato v1 §4):
 * `{eventId, sessionId, type, occurredAt, data}`.
 *
 * - `eventId` é a chave de idempotência: o gateway reenvia em caso de falha,
 *   então o mesmo evento pode chegar mais de uma vez.
 * - `type` desconhecido (evento novo do gateway) NÃO é erro: {@see self::$type}
 *   fica null e a aplicação ignora, respondendo 2xx.
 * - Os acessores tipados devolvem null quando o evento é de outro tipo.
 */
final class WebhookEvent
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $eventId,
        public readonly string $sessionId,
        public readonly ?WebhookEventType $type,
        public readonly string $rawType,
        public readonly \DateTimeImmutable $occurredAt,
        public readonly array $data,
    ) {}

    /** @throws InvalidWebhookException corpo que não é um envelope do contrato */
    public static function fromJson(string $rawBody): self
    {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            throw new InvalidWebhookException('Corpo do webhook não é JSON.');
        }

        return self::fromArray($decoded);
    }

    /**
     * @param array<string, mixed> $envelope
     * @throws InvalidWebhookException
     */
    public static function fromArray(array $envelope): self
    {
        foreach (['eventId', 'sessionId', 'type'] as $field) {
            if (!isset($envelope[$field]) || !is_string($envelope[$field]) || $envelope[$field] === '') {
                throw new InvalidWebhookException(sprintf('Webhook sem "%s".', $field));
            }
        }

        try {
            $occurredAt = new \DateTimeImmutable((string) ($envelope['occurredAt'] ?? 'now'));
        } catch (\Exception) {
            $occurredAt = new \DateTimeImmutable();
        }

        return new self(
            eventId: $envelope['eventId'],
            sessionId: $envelope['sessionId'],
            type: WebhookEventType::tryFrom($envelope['type']),
            rawType: $envelope['type'],
            occurredAt: $occurredAt,
            data: is_array($envelope['data'] ?? null) ? $envelope['data'] : [],
        );
    }

    /** @throws InvalidWebhookException `data` incompleto */
    public function message(): ?IncomingMessage
    {
        return in_array($this->type, [WebhookEventType::MESSAGE_RECEIVED, WebhookEventType::MESSAGE_SENT_FROM_DEVICE], true)
            ? $this->parse(static fn (array $d) => IncomingMessage::fromArray($d))
            : null;
    }

    /** @throws InvalidWebhookException `data` incompleto */
    public function messageStatus(): ?MessageStatusUpdate
    {
        return $this->type === WebhookEventType::MESSAGE_STATUS
            ? $this->parse(static fn (array $d) => MessageStatusUpdate::fromArray($d))
            : null;
    }

    public function contactIdentity(): ?ContactIdentity
    {
        return $this->type === WebhookEventType::CONTACT_IDENTITY ? ContactIdentity::fromArray($this->data) : null;
    }

    public function sessionStatus(): ?SessionStatusChange
    {
        return $this->type === WebhookEventType::SESSION_STATUS ? SessionStatusChange::fromArray($this->data) : null;
    }

    /**
     * @template T
     * @param callable(array<string, mixed>): T $factory
     * @return T
     */
    private function parse(callable $factory): mixed
    {
        try {
            return $factory($this->data);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidWebhookException(sprintf('Evento %s inválido: %s', $this->rawType, $e->getMessage()), 0, $e);
        }
    }
}
