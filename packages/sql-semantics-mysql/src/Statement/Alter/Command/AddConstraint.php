<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

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
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param IndexDefinition|ForeignKey|CheckConstraint $element The key or constraint
     */
    public function __construct(public readonly IndexDefinition|ForeignKey|CheckConstraint $element, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Derives the expressions of the element.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        (new ElementFacts())->element($this->element, $derivation, $scope, typeNotices: true);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ADD')->node($this->element);
    }
}
