<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;

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
 * @visibility SqlSemantics
 */
final class DefaultSetting implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnName $column The column
     * @param Scalar|null $value The new default, or null for DROP DEFAULT
     * @param bool $expression Whether the default is an expression in parentheses rather than a literal
     */
    public function __construct(public readonly ColumnName $column, public readonly ?Scalar $value, public readonly bool $expression = false)
    {
        Check::input($value !== null || !$expression, 'DROP DEFAULT has no default expression.');
    }

    /**
     * Derives the default value.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        if ($this->value !== null) {
            $derivation->scalar($this->value, $scope);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'COLUMN')->node($this->column);
        if ($this->value === null) {
            $out->keyword('DROP', 'DEFAULT');
        } elseif ($this->expression) {
            $out->keyword('SET', 'DEFAULT')->symbol('(')->node($this->value)->symbol(')');
        } else {
            $out->keyword('SET', 'DEFAULT')->node($this->value);
        }
    }
}
