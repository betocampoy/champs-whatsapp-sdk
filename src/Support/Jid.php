<?php

declare(strict_types=1);

namespace BetoCampoy\Champs\WhatsappSdk\Support;

/**
 * Identificador de um contato/grupo no WhatsApp (JID).
 *
 * Formatos vistos em produção (contrato v1 §5 regras 1 e 2):
 *  - telefone:  5516999999999@s.whatsapp.net
 *  - LID:       170712199389204@lid        (id opaco, SEM telefone)
 *  - com aparelho (device):  157857345462309:46@lid  /  5516996529659:1@s.whatsapp.net
 *  - grupo:     5516981075088-1512819916@g.us
 *
 * Imutável. {@see self::bare()} remove o sufixo de aparelho — é a forma a
 * comparar/guardar, porque o mesmo contato aparece com sufixos diferentes.
 */
final class Jid
{
    public const SERVER_PHONE = 's.whatsapp.net';
    public const SERVER_LID = 'lid';
    public const SERVER_GROUP = 'g.us';

    private function __construct(
        private readonly string $user,
        private readonly string $server,
        private readonly ?int $device,
    ) {}

    public static function parse(string $jid): self
    {
        $jid = trim($jid);
        $at = strrpos($jid, '@');
        if ($at === false || $at === 0 || $at === strlen($jid) - 1) {
            throw new \InvalidArgumentException(sprintf('JID inválido: "%s".', $jid));
        }

        $userPart = substr($jid, 0, $at);
        $server = strtolower(substr($jid, $at + 1));

        $device = null;
        if (preg_match('/^(.+):(\d+)$/', $userPart, $m)) {
            $userPart = $m[1];
            $device = (int) $m[2];
        }

        return new self($userPart, $server, $device);
    }

    public static function tryParse(?string $jid): ?self
    {
        if ($jid === null || $jid === '') {
            return null;
        }

        try {
            return self::parse($jid);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /** JID de telefone a partir de dígitos (sem resolver o 9º dígito — isso é o gateway que faz). */
    public static function fromPhone(string $phone): self
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            throw new \InvalidArgumentException(sprintf('Telefone inválido: "%s".', $phone));
        }

        return new self($digits, self::SERVER_PHONE, null);
    }

    public function user(): string
    {
        return $this->user;
    }

    public function server(): string
    {
        return $this->server;
    }

    public function device(): ?int
    {
        return $this->device;
    }

    public function isPhone(): bool
    {
        return $this->server === self::SERVER_PHONE;
    }

    public function isLid(): bool
    {
        return $this->server === self::SERVER_LID;
    }

    public function isGroup(): bool
    {
        return $this->server === self::SERVER_GROUP;
    }

    /** Só dígitos, e só quando o JID é de telefone. LID nunca revela telefone. */
    public function phone(): ?string
    {
        return $this->isPhone() ? $this->user : null;
    }

    /** Sem o sufixo de aparelho: `157857345462309:46@lid` → `157857345462309@lid`. */
    public function bare(): string
    {
        return $this->user . '@' . $this->server;
    }

    public function equals(self $other): bool
    {
        return $this->bare() === $other->bare();
    }

    public function __toString(): string
    {
        return $this->device === null ? $this->bare() : $this->user . ':' . $this->device . '@' . $this->server;
    }
}
