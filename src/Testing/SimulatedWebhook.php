<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Testing;

/**
 * Um webhook exatamente como o gateway real mandaria (corpo + headers
 * assinados), gerado pelo {@see FakeWhatsappGateway}. O teste da aplicação
 * faz o POST para `$url` (ou chama o controller direto) com isto.
 */
final class SimulatedWebhook
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $url,
        public readonly string $rawBody,
        public readonly array $headers,
    ) {}

    /** Headers no formato do `$server` do Symfony (`HTTP_X_ZAP_SIGNATURE`...), para `KernelBrowser::request()`. */
    public function serverHeaders(): array
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        foreach ($this->headers as $name => $value) {
            if (strtolower($name) === 'content-type') {
                continue;
            }
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }
}
