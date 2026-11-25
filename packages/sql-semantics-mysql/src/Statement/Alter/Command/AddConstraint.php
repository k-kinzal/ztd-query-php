<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

/**
 * `ADD key or constraint`: a request to add an index, a key, a foreign key or a check constraint.
 *
 * Mirrors PT_alter_table_add_constraint. The expressions of the element
 * (functional key parts, the check condition) are derived in the scope of
 * the changed table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility SqlSemantics
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
    public function deriveCommand(Derivation $derivation, Environment $scope, ?Name $ownerDatabase = null): void
    {
        if ($this->element instanceof ForeignKey && $ownerDatabase !== null) {
            $this->element->deriveElement($derivation, $scope);
            $this->element->references->deriveAlterReference($derivation, $ownerDatabase);

            return;
        }
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
