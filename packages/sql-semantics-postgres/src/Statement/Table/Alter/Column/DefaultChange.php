<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;

/**
 * ALTER [ COLUMN ] ... SET DEFAULT: the value a column takes when none is given.
 *
 * Mirrors `AT_ColumnDefault` with `def`. The expression sees no column, as in a column definition.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
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
