<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\FlowFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * REPEAT ... UNTIL ... END REPEAT: repeats its statements until the search condition is true.
 *
 * The facts follow MYSQL-PROGRAM-FLOW-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/repeat.html.
 *
 * Row operands remain stored program values. Their scalar-use condition is
 * checked when control reaches this instruction, after binding its operands.
 * They do not become ordinary Scalar expressions.
 *
 * @visibility public
 * @example Reading a labeled loop
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) again: REPEAT SELECT 1; UNTIL a > 0 END REPEAT again');
 *     [$create->body->label->value, count($create->body->statements)] // => ['again', 1]
 */
final class RepeatLoop implements FunctionBody
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<ProgramStatement|Statement> The statements in written order
     */
    public readonly array $statements;

    /**
     * @param Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $condition The search condition checked after every pass
     * @param list<ProgramStatement|Statement> $statements The repeated statements; at least one
     * @param Name|null $label The label written before the loop
     * @param Name|null $endLabel The label written after the loop; only a labeled loop has one
     * @throws InvalidConstruction When there is no statement, a member is of another class, or an end label is given without a label
     */
    public function __construct(public readonly Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $condition, array $statements, public readonly ?Name $label = null, public readonly ?Name $endLabel = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $this->statements = (new StatementSequence())->members($statements, 1);
        Check::input($endLabel === null || $label !== null, 'Only a labeled loop has an end label.');
    }

    /**
     * Derives the labels, the condition when there is one, and the statements.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new FlowFacts())->loop($this->label, $this->endLabel, $this->condition, $this->statements, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        (new ProgramNames())->label($out, $this->label);
        $out->keyword('REPEAT');
        (new StatementSequence())->write($out, $this->statements);
        $out->keyword('UNTIL')->node($this->condition)->keyword('END', 'REPEAT');
        (new ProgramNames())->end($out, $this->endLabel);
    }
}
