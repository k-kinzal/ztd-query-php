<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Snapshot;

/**
 * A WEIGHT_STRING template retaining a non-scalar subject and every typed option.
 *
 * An unused definition or unexecuted stored instruction keeps its dependencies.
 * The subject remains outside Scalar; a reached use must prepare its operands.
 * No reconstructed SQL, parser node or substitute scalar is retained.
 *
 * @example Retaining the non-scalar subject in a skipped instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() DO WEIGHT_STRING(ROW(1, 2), 7, 2, 64)');
 *     $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure // => true
 * @visibility public
 */
final class DeferredWeightParameters implements DeferredOperand
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $subject The string
     * @param Numeral $length The result length
     * @param Numeral $weights The number of weights
     * @param Numeral $flags The flags
     */
    public function __construct(public readonly RowOperand|DeferredOperand $subject, public readonly Numeral $length, public readonly Numeral $weights, public readonly Numeral $flags)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand; the result is a binary string.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->subject, $environment);
        return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($this->problem()), \SqlSemantics\Statement\Type\Nullability::Dependent);
    }

    /**
     * @return non-empty-list<RowOperand|DeferredOperand> The subject's retained preparation dependencies
     */
    public function operands(): array
    {
        return [$this->subject];
    }

    /**
     * Answers the first intrinsic subject constraint.
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$this->subject]);
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->keyword('WEIGHT_STRING')->glue()->symbol('(')->node($this->subject)->symbol(',')->node($this->length)->symbol(',')
            ->node($this->weights)->symbol(',')->node($this->flags)->symbol(')');
    }
}
