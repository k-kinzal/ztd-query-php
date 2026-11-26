<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * A request to drop every object of the current database that the given roles own and to revoke their privileges.
 *
 * Rule: PG-OWNED-DROP-001. Mirrors `DropOwnedStmt`: the roles and CASCADE
 * or RESTRICT as written. Which objects the roles own is known when the
 * statement runs, so no relation is resolved. Reported: PUBLIC, which is
 * not a role.
 * Source: https://www.postgresql.org/docs/17/sql-drop-owned.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropOwned implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<RoleSpec> The roles in the order written
     */
    public readonly array $roles;

    /**
     * @param list<RoleSpec> $roles The roles in the order written, at least one
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, when written
     */
    public function __construct(array $roles, public readonly ?DropBehavior $behavior = null)
    {
        $this->roles = Check::listOf($roles, RoleSpec::class, 'A role list names at least one role.', 1);
    }

    /**
     * Reports PUBLIC among the roles.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new RoleChecks())->existing($derivation, $this->roles);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'OWNED', 'BY')->list($this->roles);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
