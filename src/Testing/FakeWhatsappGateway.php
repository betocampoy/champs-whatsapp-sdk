<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Testing;

use BetoCampoy\Champs\WhatsappSdk\Contracts\WhatsappGatewayInterface;
use BetoCampoy\Champs\WhatsappSdk\Dto\GatewayHealth;
use BetoCampoy\Champs\WhatsappSdk\Dto\SendResult;
use BetoCampoy\Champs\WhatsappSdk\Dto\Session;
use BetoCampoy\Champs\WhatsappSdk\Dto\SessionOwner;
use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Enum\SessionStatus;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\SessionNotFoundException;
use BetoCampoy\Champs\WhatsappSdk\Support\ClientMessageId;
use BetoCampoy\Champs\WhatsappSdk\Support\Jid;
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;

/**
 * Gateway falso, em memória, para desenvolvimento local e testes do app
 * (sem Node, sem WhatsApp, sem rede). Imita o ciclo real:
 *
 *   startSession() → `qr` → depois de N consultas (getSession/getQrPng) → `connected`
 *
 * Também dá para forçar estados: {@see self::simulateConnected()},
 * {@see self::simulateLoggedOut()}. Envios ficam registrados em {@see self::$sent}.
 *
 * Mesmo espírito do `NullFractionwebBotClient`: nunca usar em produção.
 */
final class FakeWhatsappGateway implements WhatsappGatewayInterface
{
    /** PNG 1×1 transparente — o suficiente para uma tag <img> renderizar. */
    public const FAKE_QR_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var array<string, array{status: SessionStatus, polls: int, owner: ?SessionOwner, webhook: ?WebhookTarget}> */
    private array $sessions = [];

    /** @var list<array{sessionId: string, to: string, text: string, clientMessageId: string, waMessageId: string}> */
    public array $sent = [];

    private int $messageSeq = 0;

    /**
     * @param int         $connectAfterPolls consultas em `qr` até conectar sozinho (0 = nunca, só via simulateConnected)
     * @param string      $fakePhone         telefone que aparece como "número conectado"
     * @param string|null $stateFile         arquivo JSON onde o estado sobrevive entre requisições HTTP.
     *                                       Null (padrão) = só em memória, o que basta para testes. Para usar
     *                                       o fake NO NAVEGADOR (dev local, ex. QR "conectando" entre várias
     *                                       consultas), informe um caminho gravável, ex. `var/whatsapp-fake-gateway.json`.
     */
    public function __construct(
        private readonly int $connectAfterPolls = 3,
        private readonly string $fakePhone = '5511999990000',
        private readonly ?string $stateFile = null,
    ) {
        $this->load();
    }

    public function health(): GatewayHealth
    {
        $connected = count(array_filter($this->sessions, static fn ($s) => $s['status'] === SessionStatus::CONNECTED));

        return new GatewayHealth(true, WhatsappGatewayClient::API_VERSION, count($this->sessions), $connected);
    }

    public function startSession(string $sessionId, ?WebhookTarget $webhook = null): Session
    {
        $current = $this->sessions[$sessionId] ?? null;
        if ($current !== null && !in_array($current['status'], [SessionStatus::STOPPED, SessionStatus::LOGGED_OUT], true)) {
            return $this->toSession($sessionId);
        }

        // credenciais mantidas (parada) voltam direto conectadas, como no gateway real
        $resume = $current !== null && $current['status'] === SessionStatus::STOPPED && $current['owner'] !== null;

        $this->sessions[$sessionId] = [
            'status' => $resume ? SessionStatus::CONNECTED : SessionStatus::QR,
            'polls' => 0,
            'owner' => $resume ? $current['owner'] : null,
            'webhook' => $webhook ?? $current['webhook'] ?? null,
        ];
        $this->save();

        return $this->toSession($sessionId);
    }

    public function getSession(string $sessionId): Session
    {
        $this->poll($sessionId);

        return $this->toSession($sessionId);
    }

    public function listSessions(): array
    {
        return array_map(fn (string $id) => $this->toSession($id), array_keys($this->sessions));
    }

    public function getQrPng(string $sessionId): ?string
    {
        $this->poll($sessionId);

        return $this->sessions[$sessionId]['status'] === SessionStatus::QR
            ? base64_decode(self::FAKE_QR_PNG_BASE64)
            : null;
    }

    public function logout(string $sessionId): void
    {
        $this->require($sessionId);
        unset($this->sessions[$sessionId]);
        $this->save();
    }

    public function stopSession(string $sessionId): void
    {
        $this->require($sessionId);
        $this->sessions[$sessionId]['status'] = SessionStatus::STOPPED;
        $this->save();
    }

    public function sendText(string $sessionId, string $to, string $text, ?string $clientMessageId = null): SendResult
    {
        $this->require($sessionId);
        if ($this->sessions[$sessionId]['status'] !== SessionStatus::CONNECTED) {
            throw new GatewayException('sessão não conectada (fake)', 409, 'session_not_connected');
        }

        $clientMessageId ??= ClientMessageId::generate();

        // idempotente, como o contrato exige
        foreach ($this->sent as $item) {
            if ($item['sessionId'] === $sessionId && $item['clientMessageId'] === $clientMessageId) {
                return new SendResult($item['waMessageId'], $this->toJid($to), $clientMessageId);
            }
        }

        $waMessageId = sprintf('FAKE%012d', ++$this->messageSeq);
        $this->sent[] = compact('sessionId', 'to', 'text', 'clientMessageId', 'waMessageId');
        $this->save();

        return new SendResult($waMessageId, $this->toJid($to), $clientMessageId);
    }

    // --- controle do teste -------------------------------------------------

    public function simulateConnected(string $sessionId): void
    {
        $this->require($sessionId);
        $this->sessions[$sessionId]['status'] = SessionStatus::CONNECTED;
        $this->sessions[$sessionId]['owner'] = $this->fakeOwner();
        $this->save();
    }

    /** O usuário removeu o aparelho pelo celular. */
    public function simulateLoggedOut(string $sessionId): void
    {
        $this->require($sessionId);
        $this->sessions[$sessionId]['status'] = SessionStatus::LOGGED_OUT;
        $this->sessions[$sessionId]['owner'] = null;
        $this->save();
    }

    public function webhookOf(string $sessionId): ?WebhookTarget
    {
        return $this->sessions[$sessionId]['webhook'] ?? null;
    }

    // ------------------------------------------------------------------------

    private function poll(string $sessionId): void
    {
        $this->require($sessionId);
        $session = &$this->sessions[$sessionId];

        if ($session['status'] !== SessionStatus::QR || $this->connectAfterPolls <= 0) {
            return;
        }

        if (++$session['polls'] >= $this->connectAfterPolls) {
            $session['status'] = SessionStatus::CONNECTED;
            $session['owner'] = $this->fakeOwner();
        }
        unset($session);
        $this->save();
    }

    private function load(): void
    {
        if ($this->stateFile === null || !is_file($this->stateFile)) {
            return;
        }

        $data = json_decode((string) file_get_contents($this->stateFile), true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data['sessions'] ?? [] as $id => $s) {
            $owner = null;
            if (is_array($s['owner'] ?? null)) {
                $owner = new SessionOwner(Jid::parse($s['owner']['jid']), Jid::tryParse($s['owner']['lid'] ?? null), $s['owner']['name'] ?? null);
            }
            $webhook = is_array($s['webhook'] ?? null) ? new WebhookTarget($s['webhook']['url'], $s['webhook']['secret']) : null;

            $this->sessions[(string) $id] = [
                'status' => SessionStatus::fromGateway($s['status'] ?? null),
                'polls' => (int) ($s['polls'] ?? 0),
                'owner' => $owner,
                'webhook' => $webhook,
            ];
        }
        $this->sent = $data['sent'] ?? [];
        $this->messageSeq = (int) ($data['messageSeq'] ?? 0);
    }

    private function save(): void
    {
        if ($this->stateFile === null) {
            return;
        }

        $sessions = [];
        foreach ($this->sessions as $id => $s) {
            $sessions[$id] = [
                'status' => $s['status']->value,
                'polls' => $s['polls'],
                'owner' => $s['owner'] === null ? null : [
                    'jid' => (string) $s['owner']->jid,
                    'lid' => $s['owner']->lid?->bare(),
                    'name' => $s['owner']->name,
                ],
                'webhook' => $s['webhook'] === null ? null : ['url' => $s['webhook']->url, 'secret' => $s['webhook']->secret],
            ];
        }

        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            $this->stateFile,
            json_encode(['sessions' => $sessions, 'sent' => $this->sent, 'messageSeq' => $this->messageSeq], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            LOCK_EX,
        );
    }

    private function require(string $sessionId): void
    {
        if (!isset($this->sessions[$sessionId])) {
            throw new SessionNotFoundException('Sessão não encontrada no gateway (fake).', 404, 'session_not_found');
        }
    }

    private function toSession(string $sessionId): Session
    {
        $s = $this->sessions[$sessionId];

        return new Session($sessionId, $s['status'], $s['owner'], $s['status'] === SessionStatus::QR, null);
    }

    private function fakeOwner(): SessionOwner
    {
        return new SessionOwner(Jid::fromPhone($this->fakePhone), Jid::parse('999' . substr($this->fakePhone, -8) . '@lid'), 'Fake WhatsApp');
    }

    private function toJid(string $to): ?Jid
    {
        return str_contains($to, '@') ? Jid::tryParse($to) : Jid::fromPhone($to);
    }
}
