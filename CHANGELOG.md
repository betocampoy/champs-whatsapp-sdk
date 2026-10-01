# Changelog

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
