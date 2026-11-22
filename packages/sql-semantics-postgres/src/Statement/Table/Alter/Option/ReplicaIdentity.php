<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;

/**
 * REPLICA IDENTITY: what identifies a row in logical replication.
 *
 * Mirrors `ReplicaIdentityStmt`; an index is named only for USING INDEX.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
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
