<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Exceptions;

/**
 * O gateway respondeu com erro (4xx/5xx). Carrega o status HTTP e a
 * mensagem/código que o gateway devolveu (contrato v1 §2).
 */
class GatewayException extends WhatsappException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly ?string $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
