<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored truth test whose non-scalar operand is prepared only when reached.
 *
 * @visibility public
 * @example Keeping the truth test around a deferred stored computation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL (1 LIKE ROW(1,2)) IS TRUE');
 *     $operation->statement->body->process instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTruthTest // => true
 */
final class DeferredTruthTest implements DeferredOperand
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $operand The complete non-scalar computation
     * @param Truth $truth The truth value tested
     * @param bool $negated Whether NOT follows IS
     */
    public function __construct(public readonly RowOperand|DeferredOperand $operand, public readonly Truth $truth, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->fits($operand, Precedence::TRUTH, Precedence::BOOL_PRI), 'The truth-test operand requires grouping.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the retained operand without reporting its invocation constraint.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->operand, $environment);
        return new ScalarFact(new Invalid($this->problem()), Nullability::NotNull);
    }

    /**
     * @return non-empty-list<RowOperand|DeferredOperand> The retained computation
     */
    public function operands(): array
    {
        return [$this->operand];
    }

    /**
     * Answers the retained computation's first scalar-use constraint.
     */
    public function problem(): OperandColumns
    {
        return $this->operand instanceof DeferredOperand ? $this->operand->problem() : new OperandColumns(1, count((new RowShapes())->parts($this->operand) ?? []));
    }

    /**
     * Writes the operand and complete truth test.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('IS');
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword($this->truth->value);
    }
}
