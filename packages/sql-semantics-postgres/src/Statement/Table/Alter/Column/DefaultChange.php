<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * ALTER [ COLUMN ] ... SET DEFAULT: the value a column takes when none is given.
 *
 * Mirrors `AT_ColumnDefault` with `def`. The expression sees no column, as in a column definition.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading a new default
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ALTER a SET DEFAULT 0');
 *     $statement->toString() // => 'ALTER TABLE t ALTER a SET DEFAULT 0'
 */
final class DefaultChange implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name $column The column
     * @param Scalar $value The default expression
     */
    public function __construct(public readonly Name $column, public readonly Scalar $value)
    {
    }

    /**
     * Checks that the column exists and derives the expression where no column is visible.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Alterations())->column($derivation, $environment, $this->column);
        $derivation->scalar($this->value, $derivation->environment());
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER')->name($this->column)->keyword('SET', 'DEFAULT')->node($this->value);
    }
}
