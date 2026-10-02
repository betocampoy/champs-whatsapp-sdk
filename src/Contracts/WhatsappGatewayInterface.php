<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Contracts;

use BetoCampoy\Champs\WhatsappSdk\Dto\GatewayHealth;
use BetoCampoy\Champs\WhatsappSdk\Dto\MediaFile;
use BetoCampoy\Champs\WhatsappSdk\Dto\NumberCheck;
use BetoCampoy\Champs\WhatsappSdk\Dto\QuotedMessage;
use BetoCampoy\Champs\WhatsappSdk\Enum\MediaKind;
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
     * Alternativa ao QR: código de 8 caracteres para digitar no aparelho
     * principal (Dispositivos conectados → Conectar dispositivo → "Conectar
     * com número de telefone"). `$phone` = número da conta, com DDI (só os
     * dígitos importam). A sessão precisa estar aguardando pareamento.
     *
     * @throws SessionNotFoundException
     * @throws GatewayException 409 `pairing_unavailable` se já conectada ou fora do momento de parear
     */
    public function requestPairingCode(string $sessionId, string $phone): string;

    /**
     * O número tem WhatsApp? (sem enviar nada). Use antes de abrir conversa
     * nova; `NumberCheck::$phone` traz o número como o WhatsApp o conhece.
     *
     * @throws SessionNotFoundException
     * @throws GatewayException 409 se a sessão não está conectada
     */
    public function checkNumber(string $sessionId, string $phone): NumberCheck;

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
    public function sendText(string $sessionId, string $to, string $text, ?string $clientMessageId = null, ?QuotedMessage $quoted = null): SendResult;

    /**
     * Envia um anexo (bytes em memória; vão em base64 para o gateway local).
     * `$caption` é ignorada em áudio. Mesmas regras de `$to`/idempotência do sendText().
     *
     * @throws SessionNotFoundException
     * @throws GatewayException 400 se o arquivo passa do limite do gateway; 409/404 como no sendText()
     */
    public function sendMedia(
        string $sessionId,
        string $to,
        MediaKind $kind,
        string $bytes,
        string $mimetype,
        ?string $fileName = null,
        ?string $caption = null,
        ?string $clientMessageId = null,
        ?QuotedMessage $quoted = null,
    ): SendResult;

    /**
     * Mídia de uma mensagem recebida (`IncomingMessage::$media->available`).
     * Null quando o gateway já não tem (expirou ou nunca baixou).
     */
    public function getMedia(string $sessionId, string $waMessageId): ?MediaFile;
}
