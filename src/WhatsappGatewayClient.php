<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk;

use BetoCampoy\Champs\WhatsappSdk\Contracts\HttpClientAdapterInterface;
use BetoCampoy\Champs\WhatsappSdk\Contracts\WhatsappGatewayInterface;
use BetoCampoy\Champs\WhatsappSdk\Dto\GatewayHealth;
use BetoCampoy\Champs\WhatsappSdk\Dto\SendResult;
use BetoCampoy\Champs\WhatsappSdk\Dto\Session;
use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\AuthenticationException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\SessionNotFoundException;
use BetoCampoy\Champs\WhatsappSdk\Http\SymfonyHttpClientAdapter;
use BetoCampoy\Champs\WhatsappSdk\Support\ClientMessageId;
use BetoCampoy\Champs\WhatsappSdk\Support\Jid;

/**
 * Client HTTP do gateway `champs-whatsapp-gateway`, contrato v1
 * (`champs-whatsapp-gateway/docs/contrato-api-v1.md`).
 *
 * ```php
 * $gateway = WhatsappGatewayClient::create('http://127.0.0.1:3333', $apiKey);
 * $session = $gateway->startSession($sessionId);
 * ```
 */
final class WhatsappGatewayClient implements WhatsappGatewayInterface
{
    /** Versão do contrato que este SDK fala. */
    public const API_VERSION = '1';

    private const SESSION_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    private readonly string $baseUrl;

    public function __construct(
        string $baseUrl,
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly HttpClientAdapterInterface $http,
        private readonly float $timeout = 10.0,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        if ($this->baseUrl === '' || !preg_match('#^https?://#i', $this->baseUrl)) {
            throw new \InvalidArgumentException('baseUrl do gateway inválida (ex.: http://127.0.0.1:3333).');
        }
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey do gateway não informada.');
        }
    }

    public static function create(
        string $baseUrl,
        #[\SensitiveParameter]
        string $apiKey,
        ?HttpClientAdapterInterface $http = null,
        float $timeout = 10.0,
    ): self {
        return new self($baseUrl, $apiKey, $http ?? new SymfonyHttpClientAdapter(), $timeout);
    }

    /**
     * O gateway fala a mesma versão do contrato que este SDK? `apiVersion`
     * ausente = gateway POC, anterior ao campo: tratado como compatível.
     */
    public static function isCompatible(GatewayHealth $health): bool
    {
        return $health->apiVersion === null || $health->apiVersion === self::API_VERSION;
    }

    public function health(): GatewayHealth
    {
        // /health não exige autenticação, mas mandar o header é inofensivo
        return GatewayHealth::fromArray($this->json('GET', '/health'));
    }

    public function startSession(string $sessionId, ?WebhookTarget $webhook = null): Session
    {
        $body = $webhook === null ? [] : ['webhookUrl' => $webhook->url, 'webhookSecret' => $webhook->secret];

        return Session::fromArray($this->json('POST', $this->sessionPath($sessionId), $body));
    }

    public function getSession(string $sessionId): Session
    {
        return Session::fromArray($this->json('GET', $this->sessionPath($sessionId), sessionRoute: true));
    }

    public function listSessions(): array
    {
        $data = $this->json('GET', '/v1/sessions');

        return array_values(array_map(
            static fn (array $item) => Session::fromArray($item),
            array_filter($data, 'is_array'),
        ));
    }

    public function getQrPng(string $sessionId): ?string
    {
        $response = $this->send('GET', $this->sessionPath($sessionId) . '/qr.png');

        if ($response['status'] === 409) {
            return null; // sem QR agora (já conectada ou gerando)
        }
        $this->throwOnError($response, sessionRoute: true);

        return $response['body'];
    }

    public function logout(string $sessionId): void
    {
        $this->json('POST', $this->sessionPath($sessionId) . '/logout', [], sessionRoute: true);
    }

    public function stopSession(string $sessionId): void
    {
        $this->json('DELETE', $this->sessionPath($sessionId), sessionRoute: true);
    }

    public function sendText(string $sessionId, string $to, string $text, ?string $clientMessageId = null): SendResult
    {
        if (trim($to) === '') {
            throw new \InvalidArgumentException('Destinatário vazio.');
        }
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Texto vazio.');
        }

        $clientMessageId ??= ClientMessageId::generate();

        $data = $this->json('POST', $this->sessionPath($sessionId) . '/messages', [
            'clientMessageId' => $clientMessageId,
            'to' => $to,
            'type' => 'text',
            'text' => $text,
        ], sessionRoute: true, notFoundMayBeRecipient: true);

        if (!isset($data['waMessageId']) || !is_string($data['waMessageId'])) {
            throw new GatewayException('Gateway aceitou o envio mas não devolveu waMessageId.', 502, 'invalid_response');
        }

        return new SendResult($data['waMessageId'], Jid::tryParse($data['jid'] ?? null), $clientMessageId);
    }

    // ------------------------------------------------------------------

    private function sessionPath(string $sessionId): string
    {
        if (!preg_match(self::SESSION_ID_PATTERN, $sessionId)) {
            throw new \InvalidArgumentException(sprintf('sessionId inválido: "%s" (use [A-Za-z0-9_-], até 64).', $sessionId));
        }

        return '/v1/sessions/' . $sessionId;
    }

    /** @return array{status:int, headers:array, body:string} */
    private function send(string $method, string $path, ?array $json = null): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            'timeout' => $this->timeout,
        ];
        if ($json !== null) {
            $options['json'] = $json === [] ? new \stdClass() : $json;
        }

        return $this->http->request($method, $this->baseUrl . $path, $options);
    }

    /** @return array<mixed> */
    private function json(
        string $method,
        string $path,
        ?array $body = null,
        bool $sessionRoute = false,
        bool $notFoundMayBeRecipient = false,
    ): array {
        $response = $this->send($method, $path, $body);
        $this->throwOnError($response, $sessionRoute, $notFoundMayBeRecipient);

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new GatewayException(
                sprintf('Resposta inválida do gateway em %s %s (não é JSON).', $method, $path),
                $response['status'],
                'invalid_response',
            );
        }

        return $data;
    }

    /** @param array{status:int, headers:array, body:string} $response */
    private function throwOnError(array $response, bool $sessionRoute = false, bool $notFoundMayBeRecipient = false): void
    {
        $status = $response['status'];
        if ($status < 400) {
            return;
        }

        $data = json_decode($response['body'], true);
        $message = is_array($data) && isset($data['error']) ? (string) $data['error'] : sprintf('HTTP %d', $status);
        $code = is_array($data) && isset($data['code']) ? (string) $data['code'] : null;

        if ($status === 401) {
            throw new AuthenticationException('Gateway recusou a API key: ' . $message, 401, $code ?? 'unauthorized');
        }

        if ($status === 404 && $sessionRoute && $this->isSessionNotFound($code, $message, $notFoundMayBeRecipient)) {
            throw new SessionNotFoundException('Sessão não encontrada no gateway.', 404, 'session_not_found');
        }

        if ($status === 404 && $notFoundMayBeRecipient) {
            throw new GatewayException($message, 404, $code ?? 'recipient_not_found');
        }

        if ($status === 409 && $code === null) {
            $code = 'session_not_connected';
        }

        throw new GatewayException($message, $status, $code);
    }

    /**
     * 404 de uma rota de sessão pode ser "sessão não existe" ou, no envio,
     * "número sem WhatsApp". O contrato v1 diferencia por `code`; o POC ainda
     * não manda `code`, então cai na mensagem.
     */
    private function isSessionNotFound(?string $code, string $message, bool $notFoundMayBeRecipient): bool
    {
        if ($code !== null) {
            return $code === 'session_not_found';
        }

        return !$notFoundMayBeRecipient || str_contains(mb_strtolower($message), 'sess');
    }
}
