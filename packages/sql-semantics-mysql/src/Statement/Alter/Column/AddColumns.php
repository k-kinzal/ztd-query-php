<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

/**
 * `ADD [COLUMN] (element, …)`: a request to add several columns, and in 8.0 also keys and constraints, at the end of the table.
 *
 * Mirrors PT_alter_table_add_columns. The expressions of the elements are
 * derived in the scope of the changed table. The word COLUMN is optional
 * and always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-add-drop-column.
 *
 * @visibility SqlSemantics
 */
final class AddColumns implements AlterCommand
{
    use Snapshot;

    /**
     * @var list<TableElement> The elements in order; at least one
     */
    public readonly array $elements;

    /**
     * @param list<TableElement> $elements The elements in order; at least one
     */
    public function __construct(array $elements)
    {
        Check::input($elements !== [], 'ADD COLUMN with parentheses adds at least one element.');
        $this->elements = Check::listOf($elements, TableElement::class, 'ADD COLUMN with parentheses holds a list of table elements.');
    }

    /**
     * Derives the expressions of the elements.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        foreach ($this->elements as $element) {
            (new ElementFacts())->element($element, $derivation, $scope);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ADD', 'COLUMN')->symbol('(')->list($this->elements)->symbol(')');
    }
}
