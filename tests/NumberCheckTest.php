<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests;

use BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway;
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;
use PHPUnit\Framework\TestCase;

/** "O número tem WhatsApp?" antes de abrir conversa nova. */
final class NumberCheckTest extends TestCase
{
    public function testClienteConsultaSoComDigitosEDevolveONumeroCanonico(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);

        $http->respondJson(200, ['exists' => true, 'jid' => '551681075088@s.whatsapp.net', 'phone' => '551681075088']);
        $r = $client->checkNumber('s1', '+55 (16) 98107-5088');
        self::assertTrue($r->exists);
        self::assertSame('551681075088', $r->phone, 'o WhatsApp pode conhecer o número sem o 9');
        self::assertStringEndsWith('/v1/sessions/s1/contacts/5516981075088/exists', $http->lastCall()['url']);

        $http->respondJson(200, ['exists' => false, 'jid' => null, 'phone' => null]);
        self::assertFalse($client->checkNumber('s1', '5516900000000')->exists);
    }

    public function testFakeTemTodosMenosOsMarcados(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1');
        $gw->simulateConnected('s1');
        $gw->simulateNoWhatsapp('5516900000000');

        self::assertTrue($gw->checkNumber('s1', '5516911112222')->exists);
        self::assertFalse($gw->checkNumber('s1', '55 16 90000-0000')->exists);
    }
}
