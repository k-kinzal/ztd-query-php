<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

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
 * A standalone AS clause requires the global level. Stored program instructions
 * that defer this condition use DeferredGrantAs instead.
 *
 * @visibility public
 * @example Refusing AS at a database privilege level
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel(new \SqlSemantics\Statement\Identifier\Name('d')), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())], as: new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a routine kind at the global level
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing column privileges on a routine
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, [new \SqlSemantics\Statement\Identifier\Name('a')])], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p'))), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Granting table privileges with the grant option
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('grant select, insert on table shop.t to app@localhost with grant option')->toString() // => 'GRANT SELECT, INSERT ON shop.t TO app@localhost WITH GRANT OPTION'
 */
final class GrantPrivileges implements Statement, PrivilegeChange
{
    use Snapshot;

    /**
     * @var non-empty-list<Privilege> The granted privileges in order; ALL stands alone
     */
    public readonly array $privileges;

    /**
     * @var non-empty-list<Grantee> The accounts privileges are granted to
     */
    public readonly array $grantees;

    /**
     * @var list<WithOption> The options of the WITH clause in order
     */
    public readonly array $options;

    /**
     * @param list<Privilege> $privileges The granted privileges in order; ALL stands alone
     * @param ObjectKind $kind The kind of object the level names
     * @param PrivilegeLevel $level The privilege level
     * @param list<Grantee|UserSpecification> $grantees The accounts privileges are granted to; at least one
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
        $request = new Request\GrantRequest($privileges, $kind, $level, $grantees, $tls, $options, $as);
        $this->privileges = $request->privileges;
        $this->grantees = $request->grantees;
        $this->options = $request->options;
        Check::input($request->scopeProblem() === null, 'The privilege and object kind must fit the grant level.');
        Check::input($as === null || $level instanceof Level\GlobalLevel, 'GRANT AS applies only at the global privilege level.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the level, the table and the account clauses.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->grant($derivation, $this->privileges, $this->kind, $this->level);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->node(new Request\GrantRequest($this->privileges, $this->kind, $this->level, $this->grantees, $this->tls, $this->options, $this->as));
    }
}
