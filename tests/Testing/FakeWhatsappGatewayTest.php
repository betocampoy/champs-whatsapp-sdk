<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests\Testing;

use BetoCampoy\Champs\WhatsappSdk\Enum\SessionStatus;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\SessionNotFoundException;
use BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway;
use PHPUnit\Framework\TestCase;

final class FakeWhatsappGatewayTest extends TestCase
{
    public function testCicloQrAteConectar(): void
    {
        $gw = new FakeWhatsappGateway(connectAfterPolls: 2, fakePhone: '5516911112222');

        self::assertSame(SessionStatus::QR, $gw->startSession('s1')->status);
        self::assertNotNull($gw->getQrPng('s1'));          // 1ª consulta: ainda QR
        $session = $gw->getSession('s1');                   // 2ª consulta: conecta

        self::assertTrue($session->status->isConnected());
        self::assertSame('5516911112222', $session->owner?->phone());
        self::assertNull($gw->getQrPng('s1'));
    }

    public function testEnvioSoConectadoEIdempotente(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1');

        try {
            $gw->sendText('s1', '5516999999999', 'oi');
            self::fail('deveria recusar envio sem conexão');
        } catch (GatewayException $e) {
            self::assertSame(409, $e->getHttpStatus());
        }

        $gw->simulateConnected('s1');
        $a = $gw->sendText('s1', '5516999999999', 'oi', 'id-1');
        $b = $gw->sendText('s1', '5516999999999', 'oi', 'id-1');

        self::assertSame($a->waMessageId, $b->waMessageId);
        self::assertCount(1, $gw->sent);
    }

    public function testPararERetomarSemQr(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1');
        $gw->simulateConnected('s1');

        $gw->stopSession('s1');
        self::assertSame(SessionStatus::STOPPED, $gw->getSession('s1')->status);

        self::assertTrue($gw->startSession('s1')->status->isConnected());
    }

    public function testLogoutApagaASessao(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1');
        $gw->logout('s1');

        $this->expectException(SessionNotFoundException::class);
        $gw->getSession('s1');
    }

    public function testDesconectadoPeloCelularVoltaComQr(): void
    {
        $gw = new FakeWhatsappGateway(connectAfterPolls: 0);
        $gw->startSession('s1');
        $gw->simulateConnected('s1');
        $gw->simulateLoggedOut('s1');

        self::assertTrue($gw->getSession('s1')->status->isLoggedOut());
        self::assertSame(SessionStatus::QR, $gw->startSession('s1')->status);
    }
}
