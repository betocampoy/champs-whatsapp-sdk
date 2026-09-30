<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Contracts;

use BetoCampoy\Champs\WhatsappSdk\Exceptions\TransportException;

/**
 * Isola o SDK do client HTTP concreto (mesmo desenho do champs-jadlog-sdk).
 * A implementação padrão usa symfony/http-client; testes usam um fake.
 */
interface HttpClientAdapterInterface
{
    /**
     * @param array{headers?:array<string,string>, json?:mixed, timeout?:float|null} $options
     * @return array{status:int, headers:array<string,string|array>, body:string}
     *
     * @throws TransportException quando não foi possível falar com o gateway (rede, timeout, DNS)
     */
    public function request(string $method, string $url, array $options = []): array;
}
