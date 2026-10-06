<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\AccountChecks;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `GRANT privileges ON [kind] level TO accounts [REQUIRE …] [WITH …] [AS user [WITH ROLE …]]`: a request to grant privileges.
 *
 * Mirrors the SQLCOM_GRANT path of LEX (grant, columns, type, users_list,
 * grant_as). Rule: MYSQL-GRANT-001. The level and its table are derived by
 * MYSQL-PRIVILEGE-CHECKS-001: a table level records the resolution of the
 * table as the relation fact of the level. MySQL 5.x also writes an
 * IDENTIFIED clause per account, a REQUIRE clause and resource limits in the
 * WITH clause, which set the account as CREATE USER and ALTER USER do; their
 * problems are reported by MYSQL-ACCOUNT-CHECKS-001. The statement provides
 * no declaration. ON TABLE and ON are the same request; ALL and ALL
 * PRIVILEGES are the same request.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html,
 * https://dev.mysql.com/doc/refman/5.7/en/grant.html. Status: Implemented.
 *
 * @visibility public
 * @example Granting table privileges with the grant option
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('grant select, insert on table shop.t to app@localhost with grant option')->toString() // => 'GRANT SELECT, INSERT ON shop.t TO app@localhost WITH GRANT OPTION'
 */
final class GrantPrivileges implements Statement
{
    use Snapshot;

    /**
     * @var list<Grantable> The granted privileges in order; ALL stands alone
     */
    public readonly array $privileges;

    /**
     * @var list<UserSpecification> The accounts privileges are granted to
     */
    public readonly array $grantees;

    /**
     * @var list<WithOption> The options of the WITH clause in order
     */
    public readonly array $options;

    /**
     * @param list<Grantable> $privileges The granted privileges in order; ALL stands alone
     * @param ObjectKind $kind The kind of object the level names
     * @param PrivilegeLevel $level The privilege level
     * @param list<UserSpecification> $grantees The accounts privileges are granted to; at least one
     * @param TlsRequirement|null $tls The REQUIRE clause of MySQL 5.x, when written
     * @param list<WithOption> $options The options of the WITH clause in order
     * @param GrantAs|null $as The AS clause, when written
     */
    public function __construct(
        array $privileges,
        public readonly ObjectKind $kind,
        public readonly PrivilegeLevel $level,
        array $grantees,
        public readonly ?TlsRequirement $tls = null,
        array $options = [],
        public readonly ?GrantAs $as = null,
    ) {
        $this->privileges = Check::listOf($privileges, Grantable::class, 'GRANT names at least one privilege.', 1);
        $this->grantees = Check::listOf($grantees, UserSpecification::class, 'GRANT names at least one account.', 1);
        $this->options = Check::listOf($options, WithOption::class, 'The WITH clause lists options.');
        foreach ($this->privileges as $privilege) {
            Check::input(!$privilege instanceof AllPrivileges || count($this->privileges) === 1, 'ALL is the only item of its list.');
            Check::input(!$privilege instanceof GrantedRole || $privilege->role->host !== null, 'A role without a host in a privilege list reads as a dynamic privilege.');
        }
        foreach ($this->grantees as $grantee) {
            Check::input($grantee->granted(), 'A GRANT account is a named account with at most one authentication method.');
        }
    }

    /**
     * Derives the level, the table and the account clauses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->grant($derivation, $this->privileges, $this->kind, $this->level);
        (new AccountChecks())->tls($derivation, $this->tls);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT')->list($this->privileges)->keyword('ON');
        $keyword = $this->kind->keyword();
        if ($keyword !== null) {
            $out->keyword($keyword);
        }
        $out->node($this->level)->keyword('TO')->list($this->grantees)->node($this->tls);
        if ($this->options !== []) {
            $out->keyword('WITH');
            foreach ($this->options as $option) {
                $out->node($option);
            }
        }
        $out->node($this->as);
    }
}
