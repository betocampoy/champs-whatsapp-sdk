<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests\Webhook;

use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Enum\AckStatus;
use BetoCampoy\Champs\WhatsappSdk\Enum\SessionStatus;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\InvalidWebhookException;
use BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookEvent;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookEventType;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookTest extends TestCase
{
    private const SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testAssinaturaIgualAoGatewayNode(): void
    {
        // Mesmo cálculo do outbox.js: HMAC-SHA256(segredo, timestamp + "." + corpo)
        $body = '{"eventId":"e1"}';
        self::assertSame(
            'sha256=' . hash_hmac('sha256', '1790791732.' . $body, self::SECRET),
            WebhookVerifier::sign($body, '1790791732', self::SECRET),
        );
    }

    public function testVerificaAssinaturaValida(): void
    {
        $body = '{"x":1}';
        $ts = '1790791732';
        (new WebhookVerifier())->verify($body, $ts, WebhookVerifier::sign($body, $ts, self::SECRET), self::SECRET, now: 1790791800);
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string, ?string, ?string, int}> */
    public static function webhooksInvalidos(): iterable
    {
        $body = '{"x":1}';
        $ts = '1790791732';
        $ok = WebhookVerifier::sign($body, $ts, self::SECRET);

        yield 'sem timestamp' => [$body, null, $ok, 1790791732];
        yield 'timestamp não numérico' => [$body, 'abc', $ok, 1790791732];
        yield 'timestamp velho (> 5 min)' => [$body, $ts, $ok, 1790791732 + 301];
        yield 'timestamp no futuro (> 5 min)' => [$body, $ts, $ok, 1790791732 - 301];
        yield 'sem assinatura' => [$body, $ts, null, 1790791732];
        yield 'corpo alterado' => ['{"x":2}', $ts, $ok, 1790791732];
        yield 'segredo errado' => [$body, $ts, WebhookVerifier::sign($body, $ts, 'outro-segredo'), 1790791732];
    }

    #[DataProvider('webhooksInvalidos')]
    public function testRecusaWebhookInvalido(string $body, ?string $ts, ?string $sig, int $now): void
    {
        $this->expectException(InvalidWebhookException::class);
        (new WebhookVerifier())->verify($body, $ts, $sig, self::SECRET, now: $now);
    }

    public function testEnvelopeComTipoDesconhecidoNaoEErro(): void
    {
        $event = WebhookEvent::fromJson('{"eventId":"e1","sessionId":"s1","type":"algo.novo","occurredAt":"2026-10-01T12:00:00Z","data":{}}');

        self::assertNull($event->type);
        self::assertSame('algo.novo', $event->rawType);
        self::assertNull($event->message());
    }

    public function testEnvelopeIncompletoEInvalido(): void
    {
        $this->expectException(InvalidWebhookException::class);
        WebhookEvent::fromJson('{"sessionId":"s1","type":"message.received"}');
    }

    public function testCorpoQueNaoEJsonEInvalido(): void
    {
        $this->expectException(InvalidWebhookException::class);
        WebhookEvent::fromJson('não é json');
    }

    public function testMensagemDoContratoComLidETelefone(): void
    {
        $event = WebhookEvent::fromJson((string) json_encode([
            'eventId' => 'e1', 'sessionId' => 's1', 'type' => 'message.received', 'occurredAt' => '2026-10-01T12:00:05Z',
            'data' => [
                'waMessageId' => '3EB0ABC', 'fromMe' => false,
                'contact' => ['jid' => '170712199389204@lid', 'lid' => '170712199389204:46@lid', 'phone' => '5516997038763'],
                'pushName' => 'Fulano', 'timestamp' => 1790791732, 'messageType' => 'conversation',
                'text' => 'oi', 'hasMedia' => false, 'media' => null,
            ],
        ]));

        $msg = $event->message();
        self::assertNotNull($msg);
        self::assertSame(WebhookEventType::MESSAGE_RECEIVED, $event->type);
        self::assertSame('3EB0ABC', $msg->waMessageId);
        self::assertSame('170712199389204@lid', $msg->contact->lid?->bare(), 'LID guardado sem sufixo de aparelho');
        self::assertSame('5516997038763', $msg->contact->phone);
        self::assertSame(1790791732, $msg->sentAt->getTimestamp(), 'hora do WhatsApp, não do processamento');
        self::assertSame('oi', $msg->text);
    }

    public function testMensagemSemLidNemTelefoneEInvalida(): void
    {
        $event = WebhookEvent::fromArray([
            'eventId' => 'e1', 'sessionId' => 's1', 'type' => 'message.received',
            'data' => ['waMessageId' => 'X', 'contact' => ['jid' => 'algo@g.us']],
        ]);

        $this->expectException(InvalidWebhookException::class);
        $event->message();
    }

    public function testAckComStatusDesconhecidoEInvalido(): void
    {
        $event = WebhookEvent::fromArray(['eventId' => 'e1', 'sessionId' => 's1', 'type' => 'message.status', 'data' => ['waMessageId' => 'X', 'status' => 'xyz']]);

        $this->expectException(InvalidWebhookException::class);
        $event->messageStatus();
    }

    public function testFakeGeraWebhooksAssinadosQueOVerificadorAceita(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1', new WebhookTarget('https://app.test/hook/s1', self::SECRET));
        $verifier = new WebhookVerifier();

        $in = $gw->simulateIncomingMessage('s1', null, 'quero rastrear', lid: '999@lid');
        $verifier->verify($in->rawBody, $in->headers['X-Zap-Timestamp'], $in->headers['X-Zap-Signature'], self::SECRET);
        $msg = WebhookEvent::fromJson($in->rawBody)->message();
        self::assertSame('https://app.test/hook/s1', $in->url);
        self::assertNull($msg?->contact->phone, 'contato só com LID');
        self::assertSame('999@lid', $msg?->contact->lid?->bare());

        $identity = WebhookEvent::fromJson($gw->simulateContactIdentity('s1', '999@lid', '5516900001111')->rawBody)->contactIdentity();
        self::assertSame('5516900001111', $identity?->phone);

        $ack = WebhookEvent::fromJson($gw->simulateAck('s1', 'FAKE1', AckStatus::READ)->rawBody)->messageStatus();
        self::assertSame(AckStatus::READ, $ack?->status);
        self::assertTrue($ack?->jid?->isLid(), 'ack volta com LID, como no real');

        $status = WebhookEvent::fromJson($gw->simulateSessionStatus('s1', SessionStatus::CONNECTED)->rawBody)->sessionStatus();
        self::assertSame(SessionStatus::CONNECTED, $status?->status);
        self::assertNotNull($status?->owner);

        $device = WebhookEvent::fromJson($gw->simulateSentFromDevice('s1', '5516900001111', 'resposta pelo celular')->rawBody)->message();
        self::assertTrue($device?->fromMe);
        self::assertSame(WebhookEventType::MESSAGE_SENT_FROM_DEVICE, WebhookEvent::fromJson($gw->simulateSentFromDevice('s1', '5516900001111', 'x')->rawBody)->type);

        self::assertSame('application/json', $in->serverHeaders()['CONTENT_TYPE']);
        self::assertArrayHasKey('HTTP_X_ZAP_SIGNATURE', $in->serverHeaders());
    }

    public function testFakeSemWebhookNaoSimula(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1');

        $this->expectException(\LogicException::class);
        $gw->simulateIncomingMessage('s1', '5516900001111', 'oi');
    }
}
