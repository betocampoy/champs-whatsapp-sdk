<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Contracts;

use BetoCampoy\Champs\WhatsappSdk\Dto\GatewayHealth;
use BetoCampoy\Champs\WhatsappSdk\Dto\SendResult;
use BetoCampoy\Champs\WhatsappSdk\Dto\Session;
use BetoCampoy\Champs\WhatsappSdk\Dto\WebhookTarget;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\GatewayException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\SessionNotFoundException;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\TransportException;

/**
 * O que a aplicação usa para falar com o gateway. Injete esta interface
 * (nunca a classe concreta): em produção é o {@see \BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient},
 * em dev/testes o {@see \BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway}.
 *
 * `$sessionId`: `[A-Za-z0-9_-]{1,64}`, escolhido pela aplicação (ex. UUID).
 *
 * Todos os métodos podem lançar {@see TransportException} (gateway
 * inalcançável) e {@see GatewayException} (gateway respondeu erro).
 */
interface WhatsappGatewayInterface
{
    public function health(): GatewayHealth;

    /**
     * Cria/inicia a sessão (idempotente: sessão já ativa devolve o estado atual).
     * Depois disso a sessão fica em `qr` até alguém escanear.
     */
    public function startSession(string $sessionId, ?WebhookTarget $webhook = null): Session;

    /** @throws SessionNotFoundException */
    public function getSession(string $sessionId): Session;

    /** @return list<Session> */
    public function listSessions(): array;

    /**
     * PNG do QR atual, ou null quando não há QR (já conectada, ou ainda gerando).
     *
     * @throws SessionNotFoundException
     */
    public function getQrPng(string $sessionId): ?string;

    /**
     * Desconecta o aparelho e apaga as credenciais no gateway. Para voltar,
     * só com QR novo.
     */
    public function logout(string $sessionId): void;

    /** Para a sessão mantendo as credenciais (volta sem QR ao iniciar de novo). */
    public function stopSession(string $sessionId): void;

    /**
     * Envia texto. `$to`: dígitos (55DDNUMERO), JID de telefone ou LID.
     * `$clientMessageId` omitido = gerado aqui; guarde o do resultado para
     * reenviar com segurança (idempotente).
     *
     * @throws SessionNotFoundException
     * @throws GatewayException 409 se a sessão não está conectada; 404 se o número não tem WhatsApp
     */
    public function sendText(string $sessionId, string $to, string $text, ?string $clientMessageId = null): SendResult;
}
