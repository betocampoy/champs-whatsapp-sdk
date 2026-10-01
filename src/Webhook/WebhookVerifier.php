<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Webhook;

use BetoCampoy\Champs\WhatsappSdk\Exceptions\InvalidWebhookException;

/**
 * Confere a assinatura de um webhook do gateway (contrato v1 §4):
 *
 *   X-Zap-Timestamp: <unix segundos>
 *   X-Zap-Signature: sha256=<hex HMAC-SHA256(segredo, timestamp + "." + corpo_bruto)>
 *
 * Use SEMPRE o corpo bruto da requisição (`$request->getContent()`), nunca
 * um JSON re-serializado: um espaço diferente muda a assinatura.
 *
 * ```php
 * (new WebhookVerifier())->verify($body, $request->headers->get('X-Zap-Timestamp'),
 *     $request->headers->get('X-Zap-Signature'), $secret);
 * $event = WebhookEvent::fromJson($body);
 * ```
 */
final class WebhookVerifier
{
    public const HEADER_TIMESTAMP = 'X-Zap-Timestamp';
    public const HEADER_SIGNATURE = 'X-Zap-Signature';

    /** @param int $toleranceSeconds diferença máxima entre o timestamp e o relógio local (contrato: 5 min) */
    public function __construct(private readonly int $toleranceSeconds = 300)
    {
    }

    /** @throws InvalidWebhookException */
    public function verify(
        string $rawBody,
        ?string $timestamp,
        ?string $signature,
        #[\SensitiveParameter]
        string $secret,
        ?int $now = null,
    ): void {
        if ($secret === '') {
            throw new \InvalidArgumentException('Segredo do webhook vazio.');
        }
        if ($timestamp === null || !ctype_digit($timestamp)) {
            throw new InvalidWebhookException('Webhook sem X-Zap-Timestamp válido.');
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $this->toleranceSeconds) {
            throw new InvalidWebhookException('Webhook com timestamp fora da janela permitida.');
        }
        if ($signature === null || !hash_equals(self::sign($rawBody, $timestamp, $secret), $signature)) {
            throw new InvalidWebhookException('Assinatura do webhook inválida.');
        }
    }

    /** Valor esperado do header X-Zap-Signature (também usado pelo gateway falso). */
    public static function sign(string $rawBody, string $timestamp, #[\SensitiveParameter] string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }
}
