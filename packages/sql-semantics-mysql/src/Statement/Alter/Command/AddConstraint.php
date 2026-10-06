<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `ADD key or constraint`: a request to add an index, a key, a foreign key or a check constraint.
 *
 * Mirrors PT_alter_table_add_constraint. The expressions of the element
 * (functional key parts, the check condition) are derived in the scope of
 * the changed table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility public
 * @example Adding an index
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t ADD INDEX i (a)');
 *     $alter->toString() // => 'ALTER TABLE t ADD INDEX i (a)'
 */
final class AddConstraint implements AlterCommand
{
    use Snapshot;

    /**
     * @param TableElement $element The key or constraint
     */
    public function __construct(public readonly TableElement $element)
    {
    }

    /**
     * Derives the expressions of the element.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        (new ElementFacts())->element($this->element, $derivation, $scope);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ADD')->node($this->element);
    }
}
