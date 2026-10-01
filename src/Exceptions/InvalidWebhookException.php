<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Exceptions;

/**
 * Webhook recusado: assinatura inválida, timestamp fora da janela ou corpo
 * que não é um envelope do contrato. A aplicação responde 401/400 e NÃO
 * processa o evento.
 */
final class InvalidWebhookException extends WhatsappException
{
}
