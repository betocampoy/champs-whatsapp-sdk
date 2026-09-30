<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests\Enum;

use BetoCampoy\Champs\WhatsappSdk\Enum\AckStatus;
use PHPUnit\Framework\TestCase;

final class AckStatusTest extends TestCase
{
    public function testSoAvanca(): void
    {
        self::assertTrue(AckStatus::PENDING->shouldReplace(AckStatus::SENT));
        self::assertTrue(AckStatus::SENT->shouldReplace(AckStatus::DELIVERED));
        self::assertTrue(AckStatus::DELIVERED->shouldReplace(AckStatus::READ));
        self::assertFalse(AckStatus::READ->shouldReplace(AckStatus::DELIVERED));
        self::assertFalse(AckStatus::DELIVERED->shouldReplace(AckStatus::DELIVERED));
    }

    /**
     * Sequência real observada em produção (2026-09-30), na ordem em que
     * chegou: delivered, sent, delivered (outro aparelho). Aplicando a regra,
     * o status final tem que ser `delivered`, sem voltar para `sent`.
     */
    public function testSequenciaRealForaDeOrdem(): void
    {
        $status = AckStatus::PENDING;
        foreach ([AckStatus::DELIVERED, AckStatus::SENT, AckStatus::DELIVERED] as $ack) {
            if ($status->shouldReplace($ack)) {
                $status = $ack;
            }
        }

        self::assertSame(AckStatus::DELIVERED, $status);
    }

    public function testFailedSoAntesDeEntregar(): void
    {
        self::assertTrue(AckStatus::PENDING->shouldReplace(AckStatus::FAILED));
        self::assertTrue(AckStatus::SENT->shouldReplace(AckStatus::FAILED));
        self::assertFalse(AckStatus::DELIVERED->shouldReplace(AckStatus::FAILED));
        self::assertFalse(AckStatus::READ->shouldReplace(AckStatus::FAILED));
        self::assertFalse(AckStatus::FAILED->shouldReplace(AckStatus::FAILED));
    }

    public function testFailedAindaPodeSerCorrigidoPorEntregaTardia(): void
    {
        self::assertTrue(AckStatus::FAILED->shouldReplace(AckStatus::DELIVERED));
    }
}
