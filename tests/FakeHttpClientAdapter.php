<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Tests;

use BetoCampoy\Champs\WhatsappSdk\Contracts\HttpClientAdapterInterface;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\TransportException;

/** Adapter HTTP de teste: registra as chamadas e devolve respostas programadas, em ordem. */
final class FakeHttpClientAdapter implements HttpClientAdapterInterface
{
    /** @var list<array{method:string, url:string, options:array}> */
    public array $calls = [];

    /** @var list<array{status:int, headers:array, body:string}|\Throwable> */
    private array $queue = [];

    public function respondJson(int $status, mixed $data): self
    {
        $this->queue[] = ['status' => $status, 'headers' => ['content-type' => ['application/json']], 'body' => json_encode($data)];
        return $this;
    }

    public function respondRaw(int $status, string $body, string $contentType = 'text/plain'): self
    {
        $this->queue[] = ['status' => $status, 'headers' => ['content-type' => [$contentType]], 'body' => $body];
        return $this;
    }

    public function failTransport(string $message = 'Connection refused'): self
    {
        $this->queue[] = new TransportException($message);
        return $this;
    }

    public function request(string $method, string $url, array $options = []): array
    {
        $this->calls[] = compact('method', 'url', 'options');

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException(sprintf('Nenhuma resposta programada para %s %s.', $method, $url));
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /** @return array{method:string, url:string, options:array} */
    public function lastCall(): array
    {
        return $this->calls[array_key_last($this->calls)];
    }
}
