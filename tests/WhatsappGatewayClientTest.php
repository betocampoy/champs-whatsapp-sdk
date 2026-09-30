<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests;

use BetoCampoy\Champs\WhatsappSdk\Dto\GatewayHealth;
use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Enum\SessionStatus;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\AuthenticationException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\SessionNotFoundException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\TransportException;
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;
use PHPUnit\Framework\TestCase;

final class WhatsappGatewayClientTest extends TestCase
{
    private FakeHttpClientAdapter $http;
    private WhatsappGatewayClient $client;

    protected function setUp(): void
    {
        $this->http = new FakeHttpClientAdapter();
        $this->client = new WhatsappGatewayClient('http://127.0.0.1:3333/', 'chave-secreta', $this->http);
    }

    public function testEnviaApiKeyNoHeaderENuncaNaUrl(): void
    {
        $this->http->respondJson(200, ['ok' => true, 'sessions' => 0, 'connected' => 0]);

        $this->client->health();

        $call = $this->http->lastCall();
        self::assertSame('http://127.0.0.1:3333/health', $call['url']);
        self::assertSame('Bearer chave-secreta', $call['options']['headers']['Authorization']);
        self::assertStringNotContainsString('chave-secreta', $call['url']);
    }

    public function testHealthDoPocSemApiVersionEhCompativel(): void
    {
        // resposta real do POC em produção (2026-09-30)
        $this->http->respondJson(200, ['ok' => true, 'sessions' => 1, 'connected' => 1]);

        $health = $this->client->health();

        self::assertTrue($health->ok);
        self::assertNull($health->apiVersion);
        self::assertTrue(WhatsappGatewayClient::isCompatible($health));
        self::assertFalse(WhatsappGatewayClient::isCompatible(new GatewayHealth(true, '2', 0, 0)));
    }

    public function testStartSessionSemWebhookMandaObjetoVazio(): void
    {
        $this->http->respondJson(201, ['id' => 'abc', 'status' => 'starting', 'me' => null, 'hasQr' => false, 'lastError' => null]);

        $session = $this->client->startSession('abc');

        $call = $this->http->lastCall();
        self::assertSame('POST', $call['method']);
        self::assertSame('http://127.0.0.1:3333/v1/sessions/abc', $call['url']);
        self::assertEquals(new \stdClass(), $call['options']['json']);
        self::assertSame(SessionStatus::STARTING, $session->status);
    }

    public function testStartSessionComWebhookMandaUrlESegredo(): void
    {
        $this->http->respondJson(201, ['id' => 'abc', 'status' => 'qr', 'hasQr' => true]);
        $secret = str_repeat('a', 64);

        $this->client->startSession('abc', new WebhookTarget('https://memod.test/api/whatsapp/webhook/abc', $secret));

        self::assertSame(
            ['webhookUrl' => 'https://memod.test/api/whatsapp/webhook/abc', 'webhookSecret' => $secret],
            $this->http->lastCall()['options']['json'],
        );
    }

    public function testGetSessionConectadaDoPocTrazTelefoneELid(): void
    {
        // resposta real do POC em produção (2026-09-30)
        $this->http->respondJson(200, [
            'id' => 'teste',
            'status' => 'connected',
            'me' => ['id' => '5516996529659:1@s.whatsapp.net', 'name' => 'Beto Campoy Embaixador Wine', 'lid' => '70137671622787:1@lid'],
            'hasQr' => false,
            'lastError' => null,
        ]);

        $session = $this->client->getSession('teste');

        self::assertTrue($session->status->isConnected());
        self::assertSame('5516996529659', $session->owner?->phone());
        self::assertSame('70137671622787@lid', $session->owner?->lid?->bare());
        self::assertSame('Beto Campoy Embaixador Wine', $session->owner?->name);
    }

    public function testStatusDesconhecidoNaoQuebra(): void
    {
        $this->http->respondJson(200, ['id' => 'x', 'status' => 'algo_novo_do_gateway']);

        self::assertSame(SessionStatus::UNKNOWN, $this->client->getSession('x')->status);
    }

    public function testSessaoInexistenteViraSessionNotFound(): void
    {
        $this->http->respondJson(404, ['error' => 'sessão não encontrada']);

        $this->expectException(SessionNotFoundException::class);
        $this->client->getSession('nao-existe');
    }

    public function testApiKeyErradaViraAuthenticationException(): void
    {
        $this->http->respondJson(401, ['error' => 'unauthorized']);

        $this->expectException(AuthenticationException::class);
        $this->client->listSessions();
    }

    public function testQrPngDevolveBytesOuNullQuandoNaoHaQr(): void
    {
        $this->http->respondRaw(200, "\x89PNG-bytes", 'image/png');
        $this->http->respondJson(409, ['error' => 'sem QR (status: connected)']);

        self::assertSame("\x89PNG-bytes", $this->client->getQrPng('abc'));
        self::assertNull($this->client->getQrPng('abc'));
    }

    public function testSendTextMontaCorpoDoContratoEGeraClientMessageId(): void
    {
        // resposta real do POC em produção (2026-09-30)
        $this->http->respondJson(202, ['waMessageId' => '3EB01BC7FB0A446DFEC12A', 'jid' => '5516981075088@s.whatsapp.net']);

        $result = $this->client->sendText('teste', '5516981075088', 'Olá');

        $body = $this->http->lastCall()['options']['json'];
        self::assertSame('5516981075088', $body['to']);
        self::assertSame('text', $body['type']);
        self::assertSame('Olá', $body['text']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $body['clientMessageId']);
        self::assertSame($body['clientMessageId'], $result->clientMessageId);
        self::assertSame('3EB01BC7FB0A446DFEC12A', $result->waMessageId);
        self::assertSame('5516981075088', $result->jid?->phone());
    }

    public function testSendTextReaproveitaClientMessageIdInformado(): void
    {
        $this->http->respondJson(202, ['waMessageId' => 'X', 'jid' => '1@s.whatsapp.net']);

        $result = $this->client->sendText('teste', '1', 'oi', 'meu-id-fixo');

        self::assertSame('meu-id-fixo', $this->http->lastCall()['options']['json']['clientMessageId']);
        self::assertSame('meu-id-fixo', $result->clientMessageId);
    }

    public function testSendTextSemConexaoViraGatewayException409(): void
    {
        // resposta real do POC quando a sessão está em QR
        $this->http->respondJson(409, ['error' => 'sessão não conectada (qr)']);

        try {
            $this->client->sendText('teste', '5516981075088', 'Olá');
            self::fail('deveria lançar');
        } catch (GatewayException $e) {
            self::assertSame(409, $e->getHttpStatus());
            self::assertSame('session_not_connected', $e->getErrorCode());
        }
    }

    public function testSendTextNumeroSemWhatsappNaoEhSessionNotFound(): void
    {
        // resposta real do POC: 404 quando o número não tem WhatsApp
        $this->http->respondJson(404, ['error' => '5516000000000 não tem WhatsApp']);

        try {
            $this->client->sendText('teste', '5516000000000', 'Olá');
            self::fail('deveria lançar');
        } catch (SessionNotFoundException) {
            self::fail('404 de destinatário não pode virar SessionNotFound');
        } catch (GatewayException $e) {
            self::assertSame('recipient_not_found', $e->getErrorCode());
        }
    }

    public function testSendTextComCodigoDoContratoRespeitaOCodigo(): void
    {
        $this->http->respondJson(404, ['error' => 'x', 'code' => 'session_not_found']);

        $this->expectException(SessionNotFoundException::class);
        $this->client->sendText('teste', '1', 'oi');
    }

    public function testFalhaDeRedeViraTransportException(): void
    {
        $this->http->failTransport();

        $this->expectException(TransportException::class);
        $this->client->health();
    }

    public function testRespostaNaoJsonViraGatewayException(): void
    {
        $this->http->respondRaw(502, '<html>Bad Gateway</html>', 'text/html');

        $this->expectException(GatewayException::class);
        $this->client->getSession('abc');
    }

    public function testSessionIdInvalidoNemChegaNoGateway(): void
    {
        try {
            $this->client->getSession('../etc');
            self::fail('deveria lançar');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->http->calls);
        }
    }

    public function testListSessions(): void
    {
        $this->http->respondJson(200, [
            ['id' => 'a', 'status' => 'connected'],
            ['id' => 'b', 'status' => 'qr', 'hasQr' => true],
        ]);

        $sessions = $this->client->listSessions();

        self::assertCount(2, $sessions);
        self::assertTrue($sessions[1]->status->awaitsQr());
    }

    public function testLogoutEStop(): void
    {
        $this->http->respondJson(200, ['ok' => true]);
        $this->http->respondJson(200, ['ok' => true]);

        $this->client->logout('abc');
        self::assertSame(['POST', 'http://127.0.0.1:3333/v1/sessions/abc/logout'], [$this->http->lastCall()['method'], $this->http->lastCall()['url']]);

        $this->client->stopSession('abc');
        self::assertSame(['DELETE', 'http://127.0.0.1:3333/v1/sessions/abc'], [$this->http->lastCall()['method'], $this->http->lastCall()['url']]);
    }
}
