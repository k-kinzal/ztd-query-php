<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\User;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One authentication method of an account: `IDENTIFIED [WITH plugin] [BY 'password' | BY RANDOM PASSWORD | AS 'string']`.
 *
 * Mirrors LEX_MFA. The password or authentication string is an operand with
 * its exact value; it is never dropped or masked. A method without a plugin
 * uses the default authentication plugin of the server. MySQL 5.x also
 * writes `IDENTIFIED BY PASSWORD 'hash'`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html#create-user-authentication.
 *
 * @visibility public
 * @example Holding a plugin and its authentication string
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(new \SqlSemantics\Statement\Identifier\Name('caching_sha2_password'), \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Hash, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('$A$005$x'));
 *     [$method->plugin?->value, $method->secret?->value] // => ['caching_sha2_password', '$A$005$x']
 */
final class Identification implements Node
{
    use Snapshot;

    /**
     * @param Name|null $plugin The authentication plugin, when WITH names one
     * @param Credential $credential What the clause gives the plugin
     * @param Text|null $secret The password, hash or authentication string, for a credential that carries one
     */
    public function __construct(public readonly ?Name $plugin, public readonly Credential $credential, public readonly ?Text $secret = null)
    {
        Check::input(($secret !== null) === $credential->written(), 'A password, hash or authentication string is written exactly when the credential carries one.');
        Check::input($plugin !== null || ($credential !== Credential::None && $credential !== Credential::Hash), 'Only a named plugin is identified alone or by an authentication string.');
        Check::input($plugin === null || $credential !== Credential::PasswordHash, 'BY PASSWORD names no plugin.');
    }

    /**
     * Tells whether the method sets a password of the default plugin: `BY 'password'` or `BY RANDOM PASSWORD`.
     */
    public function password(): bool
    {
        return $this->plugin === null && ($this->credential === Credential::Password || $this->credential === Credential::RandomPassword);
    }

    /**
     * Tells whether REPLACE 'current' may follow the method: a new password, generated or written.
     */
    public function replaceable(): bool
    {
        return $this->credential === Credential::Password || $this->password();
    }

    /**
     * Tells whether RETAIN CURRENT PASSWORD may follow the method: one that gives the plugin a new secret.
     */
    public function retainable(): bool
    {
        return $this->credential !== Credential::None && $this->credential !== Credential::PasswordHash;
    }

    /**
     * Tells whether the method can be the INITIAL AUTHENTICATION of a passwordless account.
     */
    public function initial(): bool
    {
        return $this->password() || $this->credential === Credential::Hash;
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('IDENTIFIED');
        if ($this->plugin !== null) {
            $out->keyword('WITH')->name($this->plugin, NameUse::Label);
        }
        match ($this->credential) {
            Credential::None => null,
            Credential::Password => $out->keyword('BY')->node($this->secret),
            Credential::RandomPassword => $out->keyword('BY', 'RANDOM', 'PASSWORD'),
            Credential::Hash => $out->keyword('AS')->node($this->secret),
            Credential::PasswordHash => $out->keyword('BY', 'PASSWORD')->node($this->secret),
        };
    }
}
