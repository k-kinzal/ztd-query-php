<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Routine\FlowFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * ITERATE: starts the next pass of the loop that has the label.
 *
 * The facts follow MYSQL-PROGRAM-FLOW-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/iterate.html.
 *
 * @visibility public
 * @example Reading the label of the statement
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() again: LOOP ITERATE again; END LOOP');
 *     [$create->statement->body->statements[0]->label->value, $create->facts->diagnostics] // => ['again', []]
 */
final class Iterate implements ProgramStatement
{
    use Snapshot;

    /**
     * @param Name $label The label of the enclosing statement
     */
    public function __construct(public readonly Name $label)
    {
    }

    /**
     * Checks that an enclosing statement has the label.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new FlowFacts())->jump($this->label, true, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ITERATE')->name($this->label, NameUse::Label);
    }
}
