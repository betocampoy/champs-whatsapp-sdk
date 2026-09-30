<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Enum;

/** Status de uma sessão no gateway (contrato v1 §3, `Session.status`). */
enum SessionStatus: string
{
    case STARTING = 'starting';
    case QR = 'qr';
    case CONNECTED = 'connected';
    case DISCONNECTED = 'disconnected';
    case LOGGED_OUT = 'logged_out';
    case STOPPED = 'stopped';
    /** Valor que esta versão do SDK não conhece (gateway mais novo). Nunca quebra o app. */
    case UNKNOWN = 'unknown';

    public static function fromGateway(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::UNKNOWN;
    }

    public function isConnected(): bool
    {
        return $this === self::CONNECTED;
    }

    /** Precisa de leitura de QR para conectar. */
    public function awaitsQr(): bool
    {
        return $this === self::QR;
    }

    /** O aparelho foi desconectado (pelo celular ou por logout): só volta com QR novo. */
    public function isLoggedOut(): bool
    {
        return $this === self::LOGGED_OUT;
    }
}
