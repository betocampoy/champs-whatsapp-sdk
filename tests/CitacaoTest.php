<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests;

use BetoCampoy\Champs\WhatsappSdk\Dto\QuotedMessage;
use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Enum\MediaKind;
use BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookEvent;
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;
use PHPUnit\Framework\TestCase;

/** Responder citando: `quoted` no envio e `quotedWaMessageId` na mensagem recebida. */
final class CitacaoTest extends TestCase
{
    public function testEnvioCitandoMandaQuotedSoQuandoInformado(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);

        $http->respondJson(202, ['waMessageId' => 'A1']);
        $client->sendText('s1', '5516999999999', 'Sim, chega amanhã', quoted: new QuotedMessage('3EB0CLIENTE', false, str_repeat('x', 500)));
        $corpo = $http->lastCall()['options']['json'];
        self::assertSame('3EB0CLIENTE', $corpo['quoted']['waMessageId']);
        self::assertFalse($corpo['quoted']['fromMe']);
        self::assertSame(300, mb_strlen($corpo['quoted']['text']), 'trecho curto basta para o balão');

        $http->respondJson(202, ['waMessageId' => 'A2']);
        $client->sendText('s1', '5516999999999', 'sem citar');
        self::assertArrayNotHasKey('quoted', $http->lastCall()['options']['json']);

        $http->respondJson(202, ['waMessageId' => 'A3']);
        $client->sendMedia('s1', '5516999999999', MediaKind::IMAGE, 'PNG', 'image/png', quoted: new QuotedMessage('3EB0X', true, 'foto'));
        self::assertSame('3EB0X', $http->lastCall()['options']['json']['quoted']['waMessageId']);
    }

    public function testIdInvalidoNaCitacaoERecusado(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new QuotedMessage('../x', false);
    }

    public function testMensagemRecebidaCitandoTrazOIdDaCitada(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1', new WebhookTarget('https://app.test/hook', str_repeat('s', 40)));

        $msg = WebhookEvent::fromJson($gw->simulateIncomingMessage('s1', '5516900001111', 'essa aqui', quotedWaMessageId: 'FAKE000000000007')->rawBody)->message();
        self::assertSame('FAKE000000000007', $msg?->quotedWaMessageId);

        $semCitacao = WebhookEvent::fromJson($gw->simulateIncomingMessage('s1', '5516900001111', 'oi')->rawBody)->message();
        self::assertNull($semCitacao?->quotedWaMessageId);
    }

    public function testFakeRegistraACitacaoEnviada(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1');
        $gw->simulateConnected('s1');

        $gw->sendText('s1', '5516900001111', 'resposta', quoted: new QuotedMessage('3EB0ABC', false, 'pergunta'));

        self::assertSame('3EB0ABC', $gw->sent[0]['quotedWaMessageId']);
    }
}
