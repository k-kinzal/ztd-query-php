<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * REPLICA IDENTITY: what identifies a row in logical replication.
 *
 * Mirrors `ReplicaIdentityStmt`; an index is named only for USING INDEX.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Using an index as replica identity
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t REPLICA IDENTITY USING INDEX k');
 *     $statement->statement->commands[0]->index->value // => 'k'
 */
final class ReplicaIdentity implements AlterCommand
{
    use Snapshot;

    /**
     * @param ReplicaIdentityKind $kind The identity
     * @param Name|null $index The index for USING INDEX
     */
    public function __construct(public readonly ReplicaIdentityKind $kind, public readonly ?Name $index = null)
    {
        Check::input(($kind === ReplicaIdentityKind::Index) === ($index !== null), 'An index is named exactly for USING INDEX.');
    }

    /**
     * Derives nothing: the index is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('REPLICA', 'IDENTITY', ...$this->kind->keywords());
        if ($this->index !== null) {
            $out->name($this->index);
        }
    }
}
