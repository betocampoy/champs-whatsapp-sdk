<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

/**
 * Resposta de "o número tem WhatsApp?" (sem enviar nada).
 * `phone` = o número como o WhatsApp o conhece (o gateway resolve o nono
 * dígito); null quando não existe.
 */
final class NumberCheck
{
    public function __construct(
        public readonly bool $exists,
        public readonly ?string $jid = null,
        public readonly ?string $phone = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $exists = (bool) ($data['exists'] ?? false);

        return new self(
            $exists,
            $exists && is_string($data['jid'] ?? null) ? $data['jid'] : null,
            $exists && is_string($data['phone'] ?? null) ? preg_replace('/\D+/', '', $data['phone']) : null,
        );
    }
}
