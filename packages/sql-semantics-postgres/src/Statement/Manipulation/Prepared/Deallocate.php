<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * DEALLOCATE: removal of one prepared statement, or of all of them.
 *
 * Mirrors PostgreSQL's `DeallocateStmt`, whose name is NULL for ALL. The
 * keyword PREPARE after DEALLOCATE is ignored. Rule: PG-DEALLOCATE-001: the
 * prepared statements are session state; nothing is derived.
 * Source: https://www.postgresql.org/docs/17/sql-deallocate.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Deallocate implements Statement
{
    use Snapshot;

    /**
     * @param Name|null $name The name of the prepared statement; null for ALL
     */
    public function __construct(public readonly ?Name $name)
    {
    }

    /**
     * Derives nothing: prepared statements are session state.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEALLOCATE');
        if ($this->name === null) {
            $out->keyword('ALL');

            return;
        }
        $out->name($this->name, NameUse::Column);
    }
}
