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
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CREATE USER [IF NOT EXISTS] account [auth], … [DEFAULT ROLE …] [REQUIRE …] [WITH limits] [options] [ATTRIBUTE|COMMENT '…']`: a request to create accounts.
 *
 * Mirrors SQLCOM_CREATE_USER (LEX users_list, default_roles, ssl_type, mqh,
 * alter_password, alter_user_attribute). Rule: MYSQL-CREATE-USER-001. The
 * accounts, their authentication methods and every clause are kept in
 * source order; passwords keep their exact values. The problems of the
 * clauses are reported by MYSQL-ACCOUNT-CHECKS-001. An account is not a
 * relation: the statement provides no declaration.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-user.html. Status: Implemented.
 *
 * @visibility public
 * @example Creating an account with a password and a lock
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create user if not exists app@'%' identified by 'pw' account lock")->toString() // => "CREATE USER IF NOT EXISTS app@`%` IDENTIFIED BY 'pw' ACCOUNT LOCK"
 */
final class CreateUser implements Statement
{
    use Snapshot;

    /**
     * @var list<UserSpecification> The accounts in order
     */
    public readonly array $users;

    /**
     * @var list<AccountName> The roles of DEFAULT ROLE; none when the clause is absent
     */
    public readonly array $defaultRoles;

    /**
     * @var list<ResourceLimit> The resource limits of the WITH clause in order
     */
    public readonly array $resources;

    /**
     * @var list<AccountOption> The password and locking options in order
     */
    public readonly array $options;

    /**
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param list<UserSpecification> $users The accounts in order; at least one
     * @param list<AccountName> $defaultRoles The roles of DEFAULT ROLE; none when the clause is absent
     * @param TlsRequirement|null $tls The REQUIRE clause, when written
     * @param list<ResourceLimit> $resources The resource limits of the WITH clause in order
     * @param list<AccountOption> $options The password and locking options in order
     * @param UserComment|null $comment The ATTRIBUTE or COMMENT clause, when written
     */
    public function __construct(
        public readonly bool $ifNotExists,
        array $users,
        array $defaultRoles = [],
        public readonly ?TlsRequirement $tls = null,
        array $resources = [],
        array $options = [],
        public readonly ?UserComment $comment = null,
    ) {
        $this->users = Check::listOf($users, UserSpecification::class, 'CREATE USER names at least one account.', 1);
        $this->defaultRoles = Check::listOf($defaultRoles, AccountName::class, 'DEFAULT ROLE names roles.');
        $this->resources = Check::listOf($resources, ResourceLimit::class, 'The WITH clause lists resource limits.');
        $this->options = Check::listOf($options, AccountOption::class, 'The account options are a list.');
        foreach ($this->users as $user) {
            Check::input($user->created(), 'CREATE USER names accounts without password management words.');
        }
    }

    /**
     * Reports the problems of the clauses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $checks = new AccountChecks();
        $checks->tls($derivation, $this->tls);
        $checks->options($derivation, $this->options);
        $checks->comment($derivation, $this->comment);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'USER');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->list($this->users);
        if ($this->defaultRoles !== []) {
            $out->keyword('DEFAULT', 'ROLE')->list($this->defaultRoles);
        }
        $out->node($this->tls);
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
