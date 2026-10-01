<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests;

use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Enum\MediaKind;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookEvent;
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Etapa 2C: receber (getMedia, MediaInfo::$available) e enviar anexo (sendMedia). */
final class MidiaTest extends TestCase
{
    /** @return iterable<string, array{string, MediaKind}> */
    public static function mimetypes(): iterable
    {
        yield 'jpeg' => ['image/jpeg', MediaKind::IMAGE];
        yield 'png' => ['image/png', MediaKind::IMAGE];
        yield 'mp4' => ['video/mp4', MediaKind::VIDEO];
        yield 'voz ogg com codecs' => ['audio/ogg; codecs=opus', MediaKind::AUDIO];
        yield 'mp3' => ['audio/mpeg', MediaKind::AUDIO];
        yield 'pdf' => ['application/pdf', MediaKind::DOCUMENT];
        yield 'gif vira documento' => ['image/gif', MediaKind::DOCUMENT];
        yield 'maiúsculo' => ['IMAGE/JPEG', MediaKind::IMAGE];
    }

    #[DataProvider('mimetypes')]
    public function testTipoPeloMimetype(string $mimetype, MediaKind $esperado): void
    {
        self::assertSame($esperado, MediaKind::fromMimetype($mimetype));
    }

    public function testSendMediaMandaBase64ESemLegendaNoAudio(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);

        $http->respondJson(202, ['waMessageId' => 'ABC', 'jid' => '5516999999999@s.whatsapp.net']);
        $r = $client->sendMedia('s1', '5516999999999', MediaKind::DOCUMENT, '%PDF-1.4', 'application/pdf', 'boleto.pdf', 'Segue o boleto', 'cid-1');
        $corpo = $http->lastCall()['options']['json'];
        self::assertSame('ABC', $r->waMessageId);
        self::assertSame('document', $corpo['type']);
        self::assertSame(base64_encode('%PDF-1.4'), $corpo['mediaBase64']);
        self::assertSame('boleto.pdf', $corpo['fileName']);
        self::assertSame('Segue o boleto', $corpo['caption']);
        self::assertSame('cid-1', $corpo['clientMessageId']);

        $http->respondJson(202, ['waMessageId' => 'DEF']);
        $client->sendMedia('s1', '5516999999999', MediaKind::AUDIO, 'OggS', 'audio/ogg', caption: 'não vai');
        self::assertArrayNotHasKey('caption', $http->lastCall()['options']['json'], 'WhatsApp não aceita legenda em áudio');
    }

    public function testSendMediaGrandeDemaisViraGatewayException(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);
        $http->respondJson(400, ['error' => 'anexo maior que 25 MB', 'code' => 'invalid_request']);

        try {
            $client->sendMedia('s1', '5516999999999', MediaKind::IMAGE, 'x', 'image/jpeg');
            self::fail('deveria recusar');
        } catch (GatewayException $e) {
            self::assertSame('invalid_request', $e->getErrorCode());
        }
    }

    public function testGetMediaLeTipoENomeDosHeadersE404ViraNull(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);

        $http->respondRaw(200, 'bytes-da-foto', 'image/jpeg');
        $m = $client->getMedia('s1', '3EB0ABC');
        self::assertSame('bytes-da-foto', $m?->bytes);
        self::assertSame('image/jpeg', $m?->mimetype);
        self::assertStringEndsWith('/v1/sessions/s1/media/3EB0ABC', $http->lastCall()['url']);

        $http->respondJson(404, ['error' => 'mídia não encontrada', 'code' => 'media_not_found']);
        self::assertNull($client->getMedia('s1', '3EB0XYZ'));

        $this->expectException(\InvalidArgumentException::class);
        $client->getMedia('s1', '../segredo');
    }

    public function testFakeRecebeMidiaEDevolvePeloGetMedia(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1', new WebhookTarget('https://app.test/hook', str_repeat('s', 40)));

        $hook = $gw->simulateIncomingMedia('s1', '5516900001111', 'JPEGBYTES', 'image/jpeg', caption: 'foto da caixa');
        $msg = WebhookEvent::fromJson($hook->rawBody)->message();

        self::assertTrue($msg?->hasMedia);
        self::assertTrue($msg?->media?->available);
        self::assertSame('foto da caixa', $msg?->text);
        self::assertSame('JPEGBYTES', $gw->getMedia('s1', (string) $msg?->waMessageId)?->bytes);
        self::assertNull($gw->getMedia('s1', 'OUTRO'));
    }

    public function testFakeRegistraOAnexoEnviado(): void
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1');
        $gw->simulateConnected('s1');

        $gw->sendMedia('s1', '5516900001111', MediaKind::IMAGE, 'PNG', 'image/png', caption: 'olha');

        self::assertSame('image', $gw->sent[0]['kind']);
        self::assertSame(3, $gw->sent[0]['size']);
        self::assertSame('olha', $gw->sent[0]['text']);
    }
}
