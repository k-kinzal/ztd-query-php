<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\ConditionFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * GET DIAGNOSTICS: copies statement or condition information of a diagnostics area into variables.
 *
 * The statement is valid on its own and inside a stored program; it returns
 * no rows. The facts follow MYSQL-PROGRAM-CONDITIONS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html.
 *
 * @visibility public
 * @example Reading the area and the kind of information
 *     $get = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GET STACKED DIAGNOSTICS CONDITION 1 @m = MESSAGE_TEXT');
 *     [$get->statement->area->value, $get->toString()] // => ['STACKED', 'GET STACKED DIAGNOSTICS CONDITION 1 @m = MESSAGE_TEXT']
 */
final class GetDiagnostics implements Statement, ProgramStatement
{
    use Snapshot;

    /**
     * @param ConditionDiagnostics|StatementDiagnostics $information The information read
     * @param DiagnosticsArea|null $area The area keyword, when written
     */
    public function __construct(public readonly ConditionDiagnostics|StatementDiagnostics $information, public readonly ?DiagnosticsArea $area = null)
    {
    }

    /**
     * Derives the statement outside a stored program, where no local variable is in scope.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->deriveProgram($derivation, new ProgramScope($derivation->environment()));
    }

    /**
     * Derives the condition number and the targets in the scope of the statement.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        if ($this->information instanceof ConditionDiagnostics) {
            $derivation->scalar($this->information->number, $scope->environment);
        }
        (new ConditionFacts())->targets($this->information->items, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('GET');
        if ($this->area !== null) {
            $out->keyword($this->area->value);
        }
        $out->keyword('DIAGNOSTICS')->node($this->information);
    }
}
