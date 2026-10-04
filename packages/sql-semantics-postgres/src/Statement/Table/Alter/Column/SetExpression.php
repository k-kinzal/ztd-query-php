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
 * ALTER [ COLUMN ] ... SET EXPRESSION AS ( expression ): replaces the expression of a generated column.
 *
 * Mirrors `AT_SetExpression` (PostgreSQL 17). The expression is derived where the columns of the relation are
 * visible.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Replacing a generation expression
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ALTER b SET EXPRESSION AS (a * 2)');
 *     $statement->toString() // => 'ALTER TABLE t ALTER b SET EXPRESSION AS (a * 2)'
 */
final class SetExpression implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name $column The column
     * @param Scalar $expression The generation expression
     */
    public function __construct(public readonly Name $column, public readonly Scalar $expression)
    {
    }

    /**
     * Checks that the column exists and derives the expression against the relation.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Alterations())->column($derivation, $environment, $this->column);
        $derivation->scalar($this->expression, $environment);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER')->name($this->column)->keyword('SET', 'EXPRESSION', 'AS')->symbol('(')->node($this->expression)->symbol(')');
    }
}
