<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * One account of CREATE USER, ALTER USER or a MySQL 5.x GRANT, with what the statement sets for its authentication.
 *
 * Mirrors LEX_USER: the account, its first authentication method, the
 * further factors of CREATE USER (`AND IDENTIFIED …`, 8.0.27+), the initial
 * authentication of a passwordless account (`INITIAL AUTHENTICATION …`), and
 * the ALTER USER password management words: `REPLACE 'current'`, `RETAIN
 * CURRENT PASSWORD`, `DISCARD OLD PASSWORD`. Passwords are operands with
 * their exact values. The constructor accepts only the combinations the
 * grammar can write, so the rendering reads back as the same specification.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-user.html,
 * https://dev.mysql.com/doc/refman/8.4/en/multifactor-authentication.html.
 *
 * @visibility public
 * @example Reading the password of an account
 *     $user = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE USER app IDENTIFIED BY 'secret'")->statement->users[0];
 *     $user->identification?->secret?->value // => 'secret'
 */
final class UserSpecification implements UserAlteration
{
    use Snapshot;

    /**
     * @var list<Identification> The second and third authentication factors, in order
     */
    public readonly array $factors;

    /**
     * @param Account|SessionUser $user The account
     * @param Identification|null $identification The first authentication method, when written
     * @param list<Identification> $factors The further factors written with AND; at most two
     * @param Identification|null $initial The INITIAL AUTHENTICATION method of a passwordless first factor
     * @param Text|null $replace The current password REPLACE names
     * @param bool $retainCurrent Whether RETAIN CURRENT PASSWORD is written
     * @param bool $discardOld Whether DISCARD OLD PASSWORD is written
     */
    public function __construct(
        public readonly Account|SessionUser $user,
        public readonly ?Identification $identification = null,
        array $factors = [],
        public readonly ?Identification $initial = null,
        public readonly ?Text $replace = null,
        public readonly bool $retainCurrent = false,
        public readonly bool $discardOld = false,
    ) {
        $this->factors = Check::listOf($factors, Identification::class, 'The further factors are authentication methods.');
        Check::input(count($this->factors) <= 2, 'An account has at most three authentication factors.');
        Check::input($initial === null || ($identification?->credential === Credential::None && $this->factors === []), 'INITIAL AUTHENTICATION follows a plugin named alone.');
        Check::input($initial === null || $initial->initial(), 'INITIAL AUTHENTICATION sets a password or an authentication string.');
        Check::input($replace === null || ($identification?->replaceable() ?? false), 'REPLACE follows a password.');
        Check::input(!$retainCurrent || ($identification?->retainable() ?? false), 'RETAIN CURRENT PASSWORD follows a new password or authentication string.');
        Check::input(!$discardOld || $identification === null, 'DISCARD OLD PASSWORD is written alone.');
        Check::input(($replace === null && !$retainCurrent && !$discardOld) || ($this->factors === [] && $initial === null), 'Password management words and further factors belong to different statements.');
        Check::input(!$user instanceof SessionUser || ($this->factors === [] && $initial === null && ($discardOld || ($identification?->password() ?? false))), 'USER() sets its own password or discards its old one.');
    }

    /**
     * Tells whether a GRANT or REVOKE account list can write the specification: a named account with at most its first method.
     */
    public function granted(): bool
    {
        return $this->user instanceof Account && $this->factors === [] && $this->initial === null && $this->replace === null && !$this->retainCurrent && !$this->discardOld;
    }

    /**
     * Tells whether CREATE USER can write the specification: a named account without password management words.
     */
    public function created(): bool
    {
        return $this->user instanceof Account && $this->replace === null && !$this->retainCurrent && !$this->discardOld;
    }

    /**
     * Tells whether ALTER USER can write the specification: an account without further factors and initial authentication.
     */
    public function altered(): bool
    {
        return $this->factors === [] && $this->initial === null;
    }

    /**
     * Writes the account and its clauses.
     */
    public function render(Output $out): void
    {
        $out->node($this->user)->node($this->identification);
        if ($this->replace !== null) {
            $out->keyword('REPLACE')->node($this->replace);
        }
        if ($this->retainCurrent) {
            $out->keyword('RETAIN', 'CURRENT', 'PASSWORD');
        }
        if ($this->discardOld) {
            $out->keyword('DISCARD', 'OLD', 'PASSWORD');
        }
        foreach ($this->factors as $factor) {
            $out->keyword('AND')->node($factor);
        }
        if ($this->initial !== null) {
            $out->keyword('INITIAL', 'AUTHENTICATION')->node($this->initial);
        }
    }
}
