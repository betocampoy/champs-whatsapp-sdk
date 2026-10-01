<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Webhook;

/** `type` do envelope de webhook (contrato v1 §4). */
enum WebhookEventType: string
{
    case SESSION_QR = 'session.qr';
    case SESSION_STATUS = 'session.status';
    case MESSAGE_RECEIVED = 'message.received';
    case MESSAGE_SENT_FROM_DEVICE = 'message.sent_from_device';
    case MESSAGE_STATUS = 'message.status';
    case CONTACT_IDENTITY = 'contact.identity';
    case CALL_RECEIVED = 'call.received';
}
