<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation action without an operand.
 *
 * One `AlterTableCmd` without operands; see `TableActionKind`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading a relation action
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ENABLE ROW LEVEL SECURITY');
 *     $statement->statement->commands[0]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind::EnableRowSecurity
 */
final class TableAction implements AlterCommand
{
    use Snapshot;

    /**
     * @param TableActionKind $kind The action
     */
    public function __construct(public readonly TableActionKind $kind)
    {
    }

    /**
     * Derives nothing: the action has no operand.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...$this->kind->keywords());
    }
}
