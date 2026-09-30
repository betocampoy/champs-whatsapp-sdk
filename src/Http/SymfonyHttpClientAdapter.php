<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Http;

use BetoCampoy\Champs\WhatsappSdk\Contracts\HttpClientAdapterInterface;
use BetoCampoy\Champs\WhatsappSdk\Exceptions\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClient;

final class SymfonyHttpClientAdapter implements HttpClientAdapterInterface
{
    public function __construct(private ?SymfonyHttpClient $client = null) {}

    public function request(string $method, string $url, array $options = []): array
    {
        $client = $this->client ??= HttpClient::create();

        $requestOptions = [
            'headers' => $options['headers'] ?? [],
            'timeout' => $options['timeout'] ?? 10,
        ];
        if (array_key_exists('json', $options)) {
            $requestOptions['json'] = $options['json'];
        }

        try {
            $response = $client->request($method, $url, $requestOptions);

            return [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(false),
                'body' => $response->getContent(false),
            ];
        } catch (TransportExceptionInterface $e) {
            throw new TransportException(
                sprintf('Falha ao falar com o gateway WhatsApp (%s %s): %s', $method, $url, $e->getMessage()),
                previous: $e,
            );
        }
    }
}
