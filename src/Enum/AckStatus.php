<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Enum;

/**
 * Status de entrega de uma mensagem enviada (contrato v1 §4 `message.status`
 * e §5 regra 3).
 *
 * Regra do protocolo, observada em produção (2026-09-30): os acks chegam
 * FORA DE ORDEM e REPETIDOS (um por aparelho do destinatário) — veio
 * `delivered` antes de `sent`. Por isso o status só pode AVANÇAR: use
 * {@see self::shouldReplace()} antes de gravar um ack novo.
 */
enum AckStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case READ = 'read';
    case PLAYED = 'played';
    case FAILED = 'failed';

    public function rank(): int
    {
        return match ($this) {
            self::PENDING => 0,
            self::FAILED => 1,
            self::SENT => 2,
            self::DELIVERED => 3,
            self::READ => 4,
            self::PLAYED => 5,
        };
    }

    /**
     * O ack `$incoming` deve substituir o status atual `$this`?
     *
     *  - avança só para um status maior (sent < delivered < read < played);
     *  - `failed` só vale enquanto a mensagem ainda não foi entregue
     *    (um failed atrasado nunca desfaz um delivered/read);
     *  - repetido ou menor: ignora.
     */
    public function shouldReplace(self $incoming): bool
    {
        if ($incoming === self::FAILED) {
            return $this->rank() < self::DELIVERED->rank() && $this !== self::FAILED;
        }

        return $incoming->rank() > $this->rank();
    }
}
