<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Dto;

use BetoCampoy\Champs\WhatsappSdk\Support\Jid;

/**
 * O número conectado numa sessão (`Session.me` do contrato v1). O gateway
 * devolve o JID de telefone e, no Baileys v7, também o LID.
 */
final class SessionOwner
{
    public function __construct(
        public readonly Jid $jid,
        public readonly ?Jid $lid = null,
        public readonly ?string $name = null,
    ) {}

    /** @param array{id?:string, lid?:string|null, name?:string|null} $data */
    public static function fromArray(array $data): ?self
    {
        $jid = Jid::tryParse($data['id'] ?? null);
        if ($jid === null) {
            return null;
        }

        return new self($jid, Jid::tryParse($data['lid'] ?? null), $data['name'] ?? null);
    }

    /** Só dígitos (ex. 5516996529659). */
    public function phone(): ?string
    {
        return $this->jid->phone();
    }
}
