<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeChecks;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to grant privileges on objects to roles.
 *
 * Rule: PG-GRANT-001. Mirrors `GrantStmt` with `is_grant`: the privileges
 * (an empty list is ALL PRIVILEGES), the objects, the grantees, WITH GRANT
 * OPTION and GRANTED BY. The words PRIVILEGES after ALL and GROUP before a
 * grantee have no effect and are not kept. The objects are derived by their
 * target (tables are resolved, PG-GRANT-RELATION-001); the privileges are
 * checked against the object kind (PG-PRIVILEGE-CHECK-001); a grant option
 * for PUBLIC on named objects and PUBLIC as the grantor are reported.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/ddl-priv.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of a grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT ALL PRIVILEGES ON accounts TO GROUP staff WITH GRANT OPTION');
 *     [$operation->statement->privileges, $operation->statement->grantOption, $operation->toString()] // => [[], true, 'GRANT ALL ON TABLE accounts TO staff WITH GRANT OPTION']
 */
final class Grant implements Statement
{
    use Snapshot;

    /**
     * @var list<Privilege> The privileges in the order written; empty for ALL PRIVILEGES
     */
    public readonly array $privileges;

    /**
     * @var non-empty-list<RoleSpec> The grantees in the order written
     */
    public readonly array $grantees;

    /**
     * @param list<Privilege> $privileges The privileges in the order written; empty for ALL PRIVILEGES
     * @param PrivilegeTarget $target The objects the privileges are granted on
     * @param list<RoleSpec> $grantees The grantees in the order written, at least one
     * @param bool $grantOption Whether WITH GRANT OPTION is written
     * @param RoleSpec|null $grantor The role of GRANTED BY, when written
     */
    public function __construct(array $privileges, public readonly PrivilegeTarget $target, array $grantees, public readonly bool $grantOption = false, public readonly ?RoleSpec $grantor = null)
    {
        $this->privileges = (new PrivilegeLists())->privileges($privileges);
        $this->grantees = Check::listOf($grantees, RoleSpec::class, 'A grant names at least one grantee.', 1);
    }

    /**
     * Derives the objects and reports the problems of the privileges, the grant option and the grantor.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->privileges($derivation, $this->privileges, $this->target->object(), false);
        if ($this->grantOption && !$this->target instanceof SchemaContentsTarget) {
            (new PrivilegeChecks())->grantOption($derivation, $this->grantees);
        }
        if ($this->grantor !== null) {
            (new RoleChecks())->existing($derivation, [$this->grantor]);
        }
        $this->target->deriveTarget($derivation, $this->privileges);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT');
        (new PrivilegeLists())->write($out, $this->privileges);
        $out->keyword('ON')->node($this->target)->keyword('TO')->list($this->grantees);
        if ($this->grantOption) {
            $out->keyword('WITH', 'GRANT', 'OPTION');
        }
        if ($this->grantor !== null) {
            $out->keyword('GRANTED', 'BY')->node($this->grantor);
        }
    }
}
