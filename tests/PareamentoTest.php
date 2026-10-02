<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests;

use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway;
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;
use PHPUnit\Framework\TestCase;

/** Pareamento por código de 8 caracteres (alternativa ao QR). */
final class PareamentoTest extends TestCase
{
    public function testClienteMandaSoOsDigitosEDevolveOCodigo(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);

        $http->respondJson(200, ['code' => 'K7QW2M9X']);
        self::assertSame('K7QW2M9X', $client->requestPairingCode('s1', '+55 (16) 3333-1234'));
        self::assertSame(['phone' => '551633331234'], $http->lastCall()['options']['json']);
        self::assertStringEndsWith('/v1/sessions/s1/pairing-code', $http->lastCall()['url']);
    }

    public function testForaDoMomentoDeParearViraGatewayExceptionComCode(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);
        $http->respondJson(409, ['error' => 'já conectada', 'code' => 'pairing_unavailable']);

        try {
            $client->requestPairingCode('s1', '551633331234');
            self::fail('esperava GatewayException');
        } catch (GatewayException $e) {
            self::assertSame('pairing_unavailable', $e->getErrorCode());
        }
    }

    public function testTelefoneInvalidoNemChamaOGateway(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);

        $this->expectException(\InvalidArgumentException::class);
        $client->requestPairingCode('s1', '3333');
    }

    public function testFakeSoGeraCodigoAguardandoPareamento(): void
    {
        $gw = new FakeWhatsappGateway(connectAfterPolls: 0);
        $gw->startSession('s1');
        self::assertSame('FAKE1234', $gw->requestPairingCode('s1', '551633331234'));

        $gw->simulateConnected('s1');
        $this->expectException(GatewayException::class);
        $gw->requestPairingCode('s1', '551633331234');
    }
}
