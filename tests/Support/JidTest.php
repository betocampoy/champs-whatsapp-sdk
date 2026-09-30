<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests\Support;

use BetoCampoy\Champs\WhatsappSdk\Support\Jid;
use PHPUnit\Framework\TestCase;

/** Formatos reais vistos no POC em produção (2026-09-30). */
final class JidTest extends TestCase
{
    public function testTelefone(): void
    {
        $jid = Jid::parse('5516997038763@s.whatsapp.net');

        self::assertTrue($jid->isPhone());
        self::assertSame('5516997038763', $jid->phone());
        self::assertNull($jid->device());
    }

    public function testLidNuncaRevelaTelefone(): void
    {
        $jid = Jid::parse('170712199389204@lid');

        self::assertTrue($jid->isLid());
        self::assertNull($jid->phone());
    }

    public function testSufixoDeAparelhoSaiNoBare(): void
    {
        $jid = Jid::parse('157857345462309:46@lid');

        self::assertSame(46, $jid->device());
        self::assertSame('157857345462309@lid', $jid->bare());
        self::assertSame('157857345462309:46@lid', (string) $jid);
        self::assertTrue($jid->equals(Jid::parse('157857345462309@lid')));
    }

    public function testNumeroConectadoComAparelho(): void
    {
        $jid = Jid::parse('5516996529659:1@s.whatsapp.net');

        self::assertSame('5516996529659', $jid->phone());
        self::assertSame('5516996529659@s.whatsapp.net', $jid->bare());
    }

    public function testGrupo(): void
    {
        $jid = Jid::parse('5516981075088-1512819916@g.us');

        self::assertTrue($jid->isGroup());
        self::assertNull($jid->phone());
    }

    public function testFromPhoneLimpaMascara(): void
    {
        self::assertSame('5516996529659@s.whatsapp.net', Jid::fromPhone('+55 (16) 99652-9659')->bare());
    }

    public function testInvalidos(): void
    {
        self::assertNull(Jid::tryParse(''));
        self::assertNull(Jid::tryParse('sem-arroba'));
        self::assertNull(Jid::tryParse('@lid'));

        $this->expectException(\InvalidArgumentException::class);
        Jid::parse('5516@');
    }
}
