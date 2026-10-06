<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to give every object the given roles own to another role.
 *
 * Rule: PG-OWNED-REASSIGN-001. Mirrors `ReassignOwnedStmt`: the old owners
 * and the new owner. Which objects the roles own is known when the
 * statement runs, so no relation is resolved. Reported: PUBLIC, which is
 * not a role, as an old or the new owner.
 * Source: https://www.postgresql.org/docs/17/sql-reassign-owned.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the new owner
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REASSIGN OWNED BY joe, ann TO boss');
 *     [count($operation->statement->owners), $operation->statement->newOwner->name?->value] // => [2, 'boss']
 */
final class ReassignOwned implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<RoleSpec> The old owners in the order written
     */
    public readonly array $owners;

    /**
     * @param list<RoleSpec> $owners The old owners in the order written, at least one
     * @param RoleSpec $newOwner The role that receives the objects
     */
    public function __construct(array $owners, public readonly RoleSpec $newOwner)
    {
        $this->owners = Check::listOf($owners, RoleSpec::class, 'A role list names at least one role.', 1);
    }

    /**
     * Reports PUBLIC among the old owners or as the new owner.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new RoleChecks())->existing($derivation, [...$this->owners, $this->newOwner]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('REASSIGN', 'OWNED', 'BY')->list($this->owners)->keyword('TO')->node($this->newOwner);
    }
}
