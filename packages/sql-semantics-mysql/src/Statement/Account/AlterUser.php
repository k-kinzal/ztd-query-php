<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\AccountChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\SessionUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER USER [IF EXISTS] account [auth], … [REQUIRE …] [WITH limits] [options] [ATTRIBUTE|COMMENT '…']`: a request to change accounts.
 *
 * Mirrors SQLCOM_ALTER_USER. Rule: MYSQL-ALTER-USER-001. An entry sets a new
 * authentication method with the 8.0 password management words (REPLACE,
 * RETAIN CURRENT PASSWORD, DISCARD OLD PASSWORD) or changes authentication
 * factors (8.0.27+). `ALTER USER USER() …` changes the session's own
 * account and is written alone, with a new password or DISCARD OLD
 * PASSWORD. The problems of the clauses are reported by
 * MYSQL-ACCOUNT-CHECKS-001; MySQL 5.7, whose grammar shares the account list
 * of GRANT, rejects IDENTIFIED BY PASSWORD here (RejectedPasswordHash).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-user.html,
 * https://dev.mysql.com/doc/refman/5.7/en/alter-user.html. Status: Implemented.
 *
 * @visibility public
 * @example Changing a password and keeping the old one
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("alter user if exists u identified by 'new' replace 'old' retain current password")->toString() // => "ALTER USER IF EXISTS u IDENTIFIED BY 'new' REPLACE 'old' RETAIN CURRENT PASSWORD"
 */
final class AlterUser implements Statement
{
    use Snapshot;

    /**
     * @var list<UserAlteration> The account entries in order
     */
    public readonly array $users;

    /**
     * @var list<ResourceLimit> The resource limits of the WITH clause in order
     */
    public readonly array $resources;

    /**
     * @var list<AccountOption> The password and locking options in order
     */
    public readonly array $options;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<UserAlteration> $users The account entries in order; at least one
     * @param TlsRequirement|null $tls The REQUIRE clause, when written
     * @param list<ResourceLimit> $resources The resource limits of the WITH clause in order
     * @param list<AccountOption> $options The password and locking options in order
     * @param UserComment|null $comment The ATTRIBUTE or COMMENT clause, when written
     */
    public function __construct(
        public readonly bool $ifExists,
        array $users,
        public readonly ?TlsRequirement $tls = null,
        array $resources = [],
        array $options = [],
        public readonly ?UserComment $comment = null,
    ) {
        $this->users = Check::listOf($users, UserAlteration::class, 'ALTER USER names at least one account.', 1);
        $this->resources = Check::listOf($resources, ResourceLimit::class, 'The WITH clause lists resource limits.');
        $this->options = Check::listOf($options, AccountOption::class, 'The account options are a list.');
        foreach ($this->users as $user) {
            Check::input(!$user instanceof UserSpecification || $user->altered(), 'ALTER USER sets no further factors and no initial authentication.');
            Check::input(
                !$user instanceof UserSpecification || !$user->user instanceof SessionUser
                    || (count($this->users) === 1 && $tls === null && $this->resources === [] && $this->options === [] && $comment === null),
                'ALTER USER USER() is written alone.',
            );
        }
    }

    /**
     * Reports the problems of the clauses and of the factor changes.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new AccountChecks();
        foreach ($this->users as $user) {
            if ($user instanceof FactorChange) {
                $checks->factors($derivation, $user);
            } elseif ($user instanceof UserSpecification && $user->identification?->credential === Credential::PasswordHash) {
                $derivation->report(new RejectedPasswordHash());
            }
        }
        $checks->tls($derivation, $this->tls);
        $checks->options($derivation, $this->options);
        $checks->comment($derivation, $this->comment);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'USER');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->list($this->users)->node($this->tls);
        if ($this->resources !== []) {
            $out->keyword('WITH');
            foreach ($this->resources as $resource) {
                $out->node($resource);
            }
        }
        foreach ($this->options as $option) {
            $out->node($option);
        }
        $out->node($this->comment);
    }
}
