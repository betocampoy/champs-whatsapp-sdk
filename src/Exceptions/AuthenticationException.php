<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Exceptions;

/** 401: API key ausente ou inválida. Erro de configuração do app, não transitório. */
final class AuthenticationException extends GatewayException
{
}
