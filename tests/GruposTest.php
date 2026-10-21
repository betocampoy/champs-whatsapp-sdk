<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests;

use BetoCampoy\Champs\WhatsappSdk\Dto\QuotedMessage;
use BetoCampoy\Champs\WhatsappSdk\Dto\Session;
use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\InvalidWebhookException;
use BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookEvent;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookEventType;
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;
use PHPUnit\Framework\TestCase;

/** Monitoramento de grupos por sessão: opção `monitorGroups`, `Message.group` e citação com `participant`. */
final class GruposTest extends TestCase
{
    private const GRUPO = '120363041234567890@g.us';

    public function testStartSessionMandaMonitorGroupsSoQuandoInformado(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);
        $hook = new WebhookTarget('https://app.test/hook', str_repeat('s', 40));

        $http->respondJson(201, ['id' => 's1', 'status' => 'connected', 'monitorGroups' => true]);
        $session = $client->startSession('s1', $hook, true);
        self::assertTrue($http->lastCall()['options']['json']['monitorGroups']);
        self::assertTrue($session->monitorGroups);

        $http->respondJson(201, ['id' => 's1', 'status' => 'connected']);
        $client->startSession('s1', $hook, false);
        self::assertFalse($http->lastCall()['options']['json']['monitorGroups']);

        $http->respondJson(201, ['id' => 's1', 'status' => 'connected']);
        $session = $client->startSession('s1', $hook);
        self::assertArrayNotHasKey('monitorGroups', $http->lastCall()['options']['json']);
        self::assertFalse($session->monitorGroups, 'gateway antigo não manda o campo: desligado');
    }

    public function testSessionSemCampoEhDesligado(): void
    {
        self::assertFalse(Session::fromArray(['id' => 's1', 'status' => 'connected'])->monitorGroups);
    }

    public function testMensagemDeGrupoTrazOGrupoEQuemMandou(): void
    {
        $gw = $this->fake(monitorGroups: true);

        $event = WebhookEvent::fromJson($gw->simulateIncomingGroupMessage('s1', self::GRUPO, 'Entregadores SP', '5516900001111', 'cheguei no CD', 'João')->rawBody);
        $msg = $event->message();

        self::assertSame(WebhookEventType::MESSAGE_RECEIVED, $event->type);
        self::assertTrue($msg?->isGroup());
        self::assertSame(self::GRUPO, $msg?->group?->jid->bare());
        self::assertSame('Entregadores SP', $msg?->group?->subject);
        self::assertSame('5516900001111', $msg?->contact->phone, 'contact é o participante, não o grupo');
        self::assertSame('João', $msg?->pushName);
    }

    public function testMensagemDoAparelhoNoGrupoVemComOProprioNumero(): void
    {
        $gw = $this->fake(monitorGroups: true);
        $gw->simulateConnected('s1');

        $event = WebhookEvent::fromJson($gw->simulateIncomingGroupMessage('s1', self::GRUPO, null, null, 'bom dia', fromMe: true)->rawBody);
        $msg = $event->message();

        self::assertSame(WebhookEventType::MESSAGE_SENT_FROM_DEVICE, $event->type);
        self::assertTrue($msg?->fromMe);
        self::assertSame('5511999990000', $msg?->contact->phone);
        self::assertNull($msg?->group?->subject, 'subject vazio vira null');
    }

    public function testMensagem1a1NaoTemGrupo(): void
    {
        $gw = $this->fake();

        $msg = WebhookEvent::fromJson($gw->simulateIncomingMessage('s1', '5516900001111', 'oi')->rawBody)->message();

        self::assertFalse($msg?->isGroup());
        self::assertNull($msg?->group);
    }

    public function testGrupoComJidQueNaoEhDeGrupoEInvalido(): void
    {
        $event = WebhookEvent::fromArray([
            'eventId' => 'e1', 'sessionId' => 's1', 'type' => 'message.received',
            'data' => ['waMessageId' => 'X', 'contact' => ['phone' => '5516900001111'], 'group' => ['jid' => '5516900001111@s.whatsapp.net']],
        ]);

        $this->expectException(InvalidWebhookException::class);
        $event->message();
    }

    public function testCitacaoEmGrupoLevaOParticipant(): void
    {
        $http = new FakeHttpClientAdapter();
        $client = new WhatsappGatewayClient('http://127.0.0.1:3333', 'chave', $http);

        $http->respondJson(202, ['waMessageId' => 'A1']);
        $client->sendText('s1', self::GRUPO, 'anotado', quoted: new QuotedMessage('3EB0X', false, 'cheguei', '170712199389204@lid'));
        self::assertSame('170712199389204@lid', $http->lastCall()['options']['json']['quoted']['participant']);

        $http->respondJson(202, ['waMessageId' => 'A2']);
        $client->sendText('s1', '5516999999999', 'ok', quoted: new QuotedMessage('3EB0Y', false, 'oi'));
        self::assertArrayNotHasKey('participant', $http->lastCall()['options']['json']['quoted']);
    }

    public function testParticipantInvalidoERecusado(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new QuotedMessage('3EB0X', false, 'x', self::GRUPO);
    }

    public function testFakeSoEnviaParaGrupoComMonitorGroups(): void
    {
        $gw = $this->fake();
        $gw->simulateConnected('s1');

        try {
            $gw->sendText('s1', self::GRUPO, 'oi');
            self::fail('deveria recusar envio para grupo sem monitorGroups');
        } catch (GatewayException $e) {
            self::assertSame('invalid_request', $e->getErrorCode());
        }

        $gw->startSession('s1', monitorGroups: true);
        self::assertTrue($gw->monitorGroupsOf('s1'), 'sessão ativa troca a opção sem reconectar');
        $gw->sendText('s1', self::GRUPO, 'oi', quoted: new QuotedMessage('3EB0X', false, 'x', '5516900001111@s.whatsapp.net'));
        self::assertSame(self::GRUPO, $gw->sent[0]['to']);
        self::assertSame('5516900001111@s.whatsapp.net', $gw->sent[0]['quotedParticipant']);
    }

    public function testFakeGuardaAOpcaoNoArquivoDeEstado(): void
    {
        $arquivo = sys_get_temp_dir() . '/wa-fake-grupos-' . bin2hex(random_bytes(4)) . '.json';
        try {
            $gw = new FakeWhatsappGateway(stateFile: $arquivo);
            $gw->startSession('s1', null, true);

            self::assertTrue((new FakeWhatsappGateway(stateFile: $arquivo))->monitorGroupsOf('s1'));
            self::assertTrue((new FakeWhatsappGateway(stateFile: $arquivo))->getSession('s1')->monitorGroups);
        } finally {
            @unlink($arquivo);
        }
    }

    private function fake(bool $monitorGroups = false): FakeWhatsappGateway
    {
        $gw = new FakeWhatsappGateway();
        $gw->startSession('s1', new WebhookTarget('https://app.test/hook', str_repeat('s', 40)), $monitorGroups);

        return $gw;
    }
}
