<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * ALTER [ COLUMN ] ... SET STATISTICS: the statistics target of a column, or DEFAULT.
 *
 * Mirrors `AT_SetStatistics`. An index column is named by its number. PostgreSQL 17 accepts DEFAULT, which is
 * the target -1 of earlier releases.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Setting a statistics target
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER INDEX i ALTER COLUMN 1 SET STATISTICS 100');
 *     $statement->statement->commands[0]->column->digits // => '1'
 */
final class ColumnStatistics implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name|IntegerConstant $column The column, by name or, for an index, by number
     * @param SignedNumber|null $target The target; null for DEFAULT
     */
    public function __construct(public readonly Name|IntegerConstant $column, public readonly ?SignedNumber $target)
    {
    }

    /**
     * Checks that a named column exists.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Alterations())->column($derivation, $environment, $this->column);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER');
        if ($this->column instanceof Name) {
            $out->name($this->column);
        } else {
            $out->node($this->column);
        }
        $out->keyword('SET', 'STATISTICS');
        if ($this->target === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->node($this->target);
        }
    }
}
