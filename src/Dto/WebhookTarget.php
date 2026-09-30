<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

/**
 * Para onde o gateway manda os eventos de uma sessão e com qual segredo ele
 * assina (HMAC, contrato v1 §4). Informado ao criar a sessão.
 */
final class WebhookTarget
{
    public function __construct(
        public readonly string $url,
        #[\SensitiveParameter]
        public readonly string $secret,
    ) {
        if (!preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('A URL do webhook precisa começar com http:// ou https://.');
        }
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('O segredo do webhook precisa ter pelo menos 32 caracteres.');
        }
    }

    /** Segredo aleatório (64 hex) para uma sessão nova. */
    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
