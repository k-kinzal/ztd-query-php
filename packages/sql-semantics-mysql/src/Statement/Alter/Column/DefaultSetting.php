<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `ALTER [COLUMN] c SET DEFAULT value`, `SET DEFAULT (expression)` or `DROP DEFAULT`: a request to change the default of a column.
 *
 * Mirrors PT_alter_table_set_default; no value means DROP DEFAULT. A literal
 * default and a parenthesized expression default (8.0.13 and later) differ:
 * the server stores the first as a constant and evaluates the second for
 * each row, so the form is kept. The value is derived in the scope of the
 * changed table. The word COLUMN is optional and always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility public
 * @example Setting a literal default
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t ALTER a SET DEFAULT 1');
 *     [$alter->statement->commands[0]->default instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral, $alter->toString()] // => [true, 'ALTER TABLE t ALTER COLUMN a SET DEFAULT 1']
 */
final class DefaultSetting implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnName $column The column
     * @param DefaultLiteral|DefaultExpression|null $default The replacement default, or null for DROP DEFAULT
     */
    public function __construct(public readonly ColumnName $column, public readonly DefaultLiteral|DefaultExpression|null $default)
    {
    }

    /**
     * Derives the default value.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $this->column->deriveName($derivation, $scope->relations[0]->name ?? null);
        $this->default?->deriveAttribute($derivation, $scope);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'COLUMN')->node($this->column);
        if ($this->default === null) {
            $out->keyword('DROP', 'DEFAULT');
        } else {
            $out->keyword('SET')->node($this->default);
        }
    }
}
