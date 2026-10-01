# Changelog

## [0.4.0] - 2026-10-01

### Adicionado
- Responder citando: `Dto\QuotedMessage` (waMessageId, fromMe, trecho do
  texto, cortado em 300 caracteres) como último parâmetro opcional de
  `sendText()` e `sendMedia()`; vai no campo `quoted` do contrato v1.
- `Dto\Webhook\IncomingMessage::$quotedWaMessageId`: id da mensagem citada.
- `FakeWhatsappGateway`: `simulateIncomingMessage(..., quotedWaMessageId:)` e
  `quotedWaMessageId` registrado em `$sent`.

### Mudou (incompatível para quem implementa a interface)
- `sendText()` e `sendMedia()` da `WhatsappGatewayInterface` ganharam
  `?QuotedMessage $quoted = null`.

## [0.3.0] - 2026-10-01

### Adicionado
- Mídia: `getMedia()` → `Dto\MediaFile` (bytes, mimetype, nome; null quando
  o gateway já não tem) e `sendMedia()` (anexo em base64 para o gateway local).
- `Enum\MediaKind` (image/video/audio/document) com `fromMimetype()` e
  `aceitaLegenda()`.
- `Dto\Webhook\MediaInfo::$available` e `$reason` (`too_large`,
  `download_failed`): o gateway baixa a mídia antes do webhook.
- `FakeWhatsappGateway`: `simulateIncomingMedia()`, `getMedia()`, `sendMedia()`.

### Mudou (incompatível para quem implementa a interface)
- `WhatsappGatewayInterface` ganhou `sendMedia()` e `getMedia()`.

## [0.2.0] - 2026-10-01

### Adicionado
- Webhooks (contrato v1 §4): `Webhook\WebhookVerifier` (HMAC-SHA256 +
  janela de 5 min, `hash_equals`), `Webhook\WebhookEvent` (envelope; tipo
  desconhecido não é erro) e `Webhook\WebhookEventType`.
- DTOs `Dto\Webhook\IncomingMessage`, `ContactIdentity` (LID sem sufixo de
  aparelho, telefone só dígitos), `MediaInfo`, `MessageStatusUpdate`,
  `SessionStatusChange`.
- `Exceptions\InvalidWebhookException`.
- `FakeWhatsappGateway`: `simulateIncomingMessage()`, `simulateSentFromDevice()`,
  `simulateAck()`, `simulateContactIdentity()`, `simulateSessionStatus()`,
  devolvendo `Testing\SimulatedWebhook` (corpo + headers assinados).
- `GatewayHealth::$pendingWebhooks`.

## [0.1.0] - 2026-09-30

### Adicionado
- `WhatsappGatewayClient` (contrato v1 do `champs-whatsapp-gateway`): `health`,
  `startSession`, `getSession`, `listSessions`, `getQrPng`, `logout`,
  `stopSession`, `sendText`.
- `Contracts\WhatsappGatewayInterface` e `Contracts\HttpClientAdapterInterface`
  (+ `Http\SymfonyHttpClientAdapter`).
- DTOs `Session`, `SessionOwner`, `GatewayHealth`, `SendResult`, `WebhookTarget`.
- Enums `SessionStatus` (com `UNKNOWN` para valores futuros) e `AckStatus`
  (`shouldReplace()`: status de entrega só avança).
- `Support\Jid` (telefone × LID × grupo × sufixo de aparelho) e
  `Support\ClientMessageId`.
- Exceções `WhatsappException`, `TransportException`, `GatewayException`,
  `AuthenticationException`, `SessionNotFoundException`.
- `Testing\FakeWhatsappGateway` para desenvolvimento e testes das aplicações,
  com `stateFile` opcional para o estado sobreviver entre requisições HTTP
  (uso no navegador).
- Testes: 36 unitários + teste manual contra o gateway POC real.
