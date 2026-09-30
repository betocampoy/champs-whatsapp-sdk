<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Exceptions;

/**
 * 404 numa rota de sessão: a sessão não existe no gateway (ou pertence a
 * outra aplicação, contrato v1 §1 — o gateway não diferencia os dois casos).
 */
final class SessionNotFoundException extends GatewayException
{
}
