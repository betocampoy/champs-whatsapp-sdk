<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto\Webhook;

/**
 * Localização de uma mensagem (`locationMessage` ou `liveLocationMessage`),
 * em graus decimais. Em tempo real (`live`) é só a posição de quando a
 * mensagem chegou.
 *
 * Dado pessoal (onde o cliente está): nunca logar.
 */
final class LocationInfo
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly bool $live = false,
    ) {}

    /**
     * Null quando a coordenada falta ou está fora da faixa: o gateway já
     * filtra, mas um payload ruim não pode virar ponto no mapa.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        if (!is_numeric($data['latitude'] ?? null) || !is_numeric($data['longitude'] ?? null)) {
            return null;
        }
        $lat = (float) $data['latitude'];
        $lng = (float) $data['longitude'];
        if (abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        return new self(
            latitude: $lat,
            longitude: $lng,
            name: isset($data['name']) && $data['name'] !== '' ? (string) $data['name'] : null,
            address: isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null,
            live: (bool) ($data['live'] ?? false),
        );
    }
}
