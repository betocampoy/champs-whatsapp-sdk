# betocampoy/champs-whatsapp-sdk

SDK PHP para o gateway WhatsApp **`champs-whatsapp-gateway`** (Node + Baileys).
Fala o **contrato v1** do gateway (`champs-whatsapp-gateway/docs/contrato-api-v1.md`)
e concentra as regras do protocolo do WhatsApp que qualquer aplicação precisa
seguir. Serve para qualquer projeto PHP do ecossistema (Symfony ou legado).

**O que o SDK é:** a camada de comunicação. **O que ele não é:** ele não
conhece tenant, Unidade, usuário, atendimento, Doctrine, banco nem fila.
Isso é da aplicação.

```
 3. Domínio da aplicação   (ex.: módulo WhatsApp do MEMOD)   ← seu código
 2. Adaptador da aplicação (rota do webhook, persistência)   ← seu código, fino
 1. champs-whatsapp-sdk    (este pacote)
 0. champs-whatsapp-gateway (serviço Node, um por servidor, acesso local)
```

## Requisitos

- PHP >= 8.2
- `symfony/http-client` ^6.4 ou ^7 (instalado junto; usado por um adapter trocável)
- Um gateway `champs-whatsapp-gateway` acessível (normalmente `http://127.0.0.1:3333`)
  e a API key dele

## Instalação

```bash
composer require betocampoy/champs-whatsapp-sdk
```

Durante o desenvolvimento do pacote, o projeto consumidor pode apontar para a
cópia local (path repository, mudanças aparecem na hora):

```json
{
  "repositories": [
    { "type": "path", "url": "../champs-whatsapp-sdk", "options": { "symlink": true } }
  ],
  "require": { "betocampoy/champs-whatsapp-sdk": "*@dev" }
}
```

## Uso rápido

```php
use BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient;

$gateway = WhatsappGatewayClient::create('http://127.0.0.1:3333', $apiKey);

$session = $gateway->startSession($sessionId);   // fica em "qr"
$png = $gateway->getQrPng($sessionId);           // bytes do PNG, ou null se não há QR
$session = $gateway->getSession($sessionId);     // consulte até isConnected()

if ($session->status->isConnected()) {
    echo $session->owner?->phone();              // 5516996529659
    $result = $gateway->sendText($sessionId, '5516981075088', 'Olá!');
    // guarde $result->waMessageId (para casar o ack) e $result->clientMessageId (reenvio seguro)
}
```

`$sessionId`: `[A-Za-z0-9_-]{1,64}`, escolhido pela aplicação (ex. UUID).

### Symfony (`config/services.yaml`)

```yaml
BetoCampoy\Champs\WhatsappSdk\Contracts\WhatsappGatewayInterface:
    factory: ['BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient', 'create']
    arguments:
        $baseUrl: '%env(MEU_APP_WHATSAPP_GATEWAY_BASE_URL)%'
        $apiKey: '%env(MEU_APP_WHATSAPP_GATEWAY_API_KEY)%'   # segredo: .env.local

# dev/teste: sem gateway, sem WhatsApp
when@test:
    services:
        BetoCampoy\Champs\WhatsappSdk\Contracts\WhatsappGatewayInterface:
            class: BetoCampoy\Champs\WhatsappSdk\Testing\FakeWhatsappGateway
```

Injete sempre a **interface** `WhatsappGatewayInterface`, nunca a classe.

### Legado (PHP puro)

```php
require 'vendor/autoload.php';
$gateway = \BetoCampoy\Champs\WhatsappSdk\WhatsappGatewayClient::create($url, $apiKey);
```

## API (`WhatsappGatewayInterface`)

| Método | Retorno | Observação |
|---|---|---|
| `health()` | `GatewayHealth` | `WhatsappGatewayClient::isCompatible($health)` confere a versão do contrato |
| `startSession($id, ?WebhookTarget)` | `Session` | idempotente. `WebhookTarget(url, secret)` diz para onde o gateway manda os eventos |
| `getSession($id)` | `Session` | `status`, `owner` (telefone, LID, nome), `hasQr`, `lastError` |
| `listSessions()` | `Session[]` | |
| `getQrPng($id)` | `?string` | PNG, ou `null` quando não há QR (já conectada ou gerando) |
| `logout($id)` | `void` | desconecta o aparelho e apaga as credenciais (volta só com QR novo) |
| `stopSession($id)` | `void` | para, mantendo as credenciais (volta sem QR) |
| `sendText($id, $to, $text, ?$clientMessageId)` | `SendResult` | `$to`: dígitos, JID ou LID. **202 = aceito, não entregue** |

### Erros

Todas as exceções estendem `WhatsappException`:

| Exceção | Quando |
|---|---|
| `TransportException` | gateway inalcançável (fora do ar, timeout, rede) |
| `AuthenticationException` | API key inválida (401). Erro de configuração |
| `SessionNotFoundException` | sessão não existe no gateway |
| `GatewayException` | outro erro do gateway. `getHttpStatus()` e `getErrorCode()`: `session_not_connected` (409), `recipient_not_found` (404), ... |

## Regras do protocolo (use o SDK, não reimplemente)

Observadas no teste real em produção (2026-09-30), fazem parte do contrato:

1. **Contato pode não ter telefone.** No Baileys v7 muitos contatos chegam só
   com LID (`170712199389204@lid`). Nunca use o telefone como única chave.
   `Support\Jid` separa telefone, LID, grupo e sufixo de aparelho
   (`Jid::parse('157857345462309:46@lid')->bare()` → `157857345462309@lid`).
2. **Ack casa por `waMessageId`**, nunca pelo destinatário: o ack de uma
   mensagem enviada para um telefone volta com o LID.
3. **Status de entrega só avança.** Acks chegam fora de ordem e repetidos.
   Antes de gravar um ack: `$atual->shouldReplace($novo)` (`Enum\AckStatus`).
4. **Envio é idempotente por `clientMessageId`.** Gere antes de enviar
   (`Support\ClientMessageId::generate()`), guarde, e reenvie com o mesmo id.

## Receber webhooks

O gateway manda os eventos de cada sessão para o `WebhookTarget` informado no
`startSession()`, assinados com o segredo dele. Na rota da aplicação:

```php
use BetoCampoy\Champs\WhatsappSdk\Exceptions\InvalidWebhookException;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookEvent;
use BetoCampoy\Champs\WhatsappSdk\Webhook\WebhookVerifier;

$body = $request->getContent();                 // corpo BRUTO, nunca re-serializado
try {
    (new WebhookVerifier())->verify(
        $body,
        $request->headers->get(WebhookVerifier::HEADER_TIMESTAMP),
        $request->headers->get(WebhookVerifier::HEADER_SIGNATURE),
        $secretDaSessao,                        // o mesmo do WebhookTarget
    );
    $event = WebhookEvent::fromJson($body);
} catch (InvalidWebhookException) {
    return new Response('', 401);
}
// grave $event->eventId (único) e responda 200 JÁ; processe depois (fila)
```

No processamento:

| `$event->type` (`WebhookEventType`) | Acessor | O quê |
|---|---|---|
| `MESSAGE_RECEIVED` / `MESSAGE_SENT_FROM_DEVICE` | `message()` → `IncomingMessage` | `contact` (`ContactIdentity`: `lid`, `phone`, um dos dois pode ser null), `text`, `sentAt` (hora do WhatsApp), `media` |
| `MESSAGE_STATUS` | `messageStatus()` → `MessageStatusUpdate` | ack: case por `waMessageId`, grave só se `shouldReplace()` |
| `CONTACT_IDENTITY` | `contactIdentity()` | par LID ↔ telefone descoberto depois: una os contatos |
| `SESSION_STATUS` | `sessionStatus()` → `SessionStatusChange` | conectou, caiu, deslogado pelo celular |
| `null` | — | tipo novo que este SDK não conhece: ignore e responda 2xx |

Regras do envelope: `eventId` repetido = já processado (o gateway reenvia
quando não recebe 2xx); responda **2xx** também para repetido e para tipo
desconhecido. Um `4xx` faz o gateway desistir do evento.

## Testes na aplicação: `FakeWhatsappGateway`

Gateway falso em memória, para desenvolvimento local e testes da aplicação
(sem Node, sem WhatsApp):

```php
$fake = new FakeWhatsappGateway(connectAfterPolls: 3, fakePhone: '5511999990000');
$fake->startSession('s1');          // qr
$fake->getSession('s1');            // ... na 3ª consulta: connected
$fake->simulateConnected('s1');     // ou força
$fake->simulateLoggedOut('s1');     // aparelho removido pelo celular
$fake->sendText('s1', '5511...', 'oi');
$fake->sent;                        // envios registrados

// no navegador (dev local), para o estado sobreviver entre requisições HTTP:
$fake = new FakeWhatsappGateway(stateFile: __DIR__ . "/var/whatsapp-fake-gateway.json");

// webhooks exatamente como o gateway mandaria (corpo + headers assinados).
// O fake não faz HTTP: o teste envia para a rota da aplicação.
$fake->startSession('s1', new WebhookTarget('https://app.test/hook/s1', $segredo));
$hook = $fake->simulateIncomingMessage('s1', '5516900001111', 'quero rastrear');
$hook = $fake->simulateIncomingMessage('s1', null, 'oi', lid: '999@lid');   // contato só com LID
$fake->simulateAck('s1', $waMessageId, AckStatus::READ);
$fake->simulateContactIdentity('s1', '999@lid', '5516900001111');
$fake->simulateSentFromDevice('s1', '5516900001111', 'respondi pelo celular');
$fake->simulateSessionStatus('s1', SessionStatus::LOGGED_OUT);
$client->request('POST', $hook->url, server: $hook->serverHeaders(), content: $hook->rawBody);  // Symfony KernelBrowser
```

Para ver o fluxo **real** (QR de verdade com um número de teste), suba o
gateway localmente (Node >= 20) e aponte o `baseUrl` para ele.

## Versões

- SDK `0.x`/`1.x` fala o **contrato v1** do gateway.
- `GatewayHealth::$apiVersion` nulo = gateway POC, anterior ao campo: aceito.

## Roadmap

- **0.1:** sessões, QR, status, envio de texto, regras de protocolo (`Jid`,
  `AckStatus`, `ClientMessageId`), fake.
- **0.2:** webhooks: `Webhook\WebhookVerifier`, `Webhook\WebhookEvent` com DTOs
  tipados, webhooks simulados no fake.
- **0.3:** mídia: `getMedia()` (o gateway baixa na chegada e guarda por 24 h:
  busque logo e guarde do seu lado), `sendMedia()` com `MediaKind::fromMimetype()`.
- **0.4:** responder citando: `Dto\QuotedMessage` no último parâmetro de
  `sendText()`/`sendMedia()`; `IncomingMessage::$quotedWaMessageId` quando o
  cliente responde citando uma mensagem.
- **0.5:** `requestPairingCode()`: conectar por código de 8 caracteres em vez do QR.
- **0.6:** `checkNumber()`: o número tem WhatsApp? (antes de abrir conversa nova).
- Depois: marcar como lido, presença, foto de perfil.

## Desenvolvimento

```bash
composer install
vendor/bin/phpunit
```

## Licença

MIT
