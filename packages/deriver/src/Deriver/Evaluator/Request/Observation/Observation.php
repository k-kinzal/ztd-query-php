<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Request\Observation;

use Deriver\Exception\InvalidInputException;

/**
 * A source call or object creation site with references to its once-evaluated argument values.
 *
 * @visibility root
 * @example Constructing an invocation point
 *     $source = new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 1);
 *     $observation = new \Deriver\Deriver\Evaluator\Request\Observation\Observation($source, 'run', 'i1', 'sink', []);
 *     $observation->beforeInvocation()->phase === \Deriver\Deriver\Lowerer\Graph\ObservationPhase::Invocation // => true
 */
final class Observation
{
    /**
     * @param \Deriver\Deriver\Sources\SourceRef $source Call expression range
     * @param string $callable Owning callable
     * @param string $instruction Call instruction identity
     * @param string $target Statically known function or method name, the created class for `new`, or empty for a dynamic call
     * @param array<int|string, \Deriver\Deriver\Lowerer\Graph\ExpressionRef> $arguments Original argument expression references
     * @param \Deriver\Deriver\Lowerer\Graph\ExpressionRef|null $returned Return expression reference; the created object for `new`
     * @param \Deriver\Deriver\Lowerer\Graph\ExpressionRef|null $receiver Evaluated object or class operand for a method call; null for functions and `new`
     * @param string $operation Invocation kind: invoke, invoke-method, invoke-static, or new
     */
    public function __construct(public readonly \Deriver\Deriver\Sources\SourceRef $source, public readonly string $callable, public readonly string $instruction, public readonly string $target, public readonly array $arguments, public readonly ?\Deriver\Deriver\Lowerer\Graph\ExpressionRef $returned = null, public readonly ?\Deriver\Deriver\Lowerer\Graph\ExpressionRef $receiver = null, public readonly string $operation = 'invoke')
    {
    }

    /**
     * Refers to an evaluated argument, not a re-executed expression.
     * @param int|string $name Original argument index or supplied name
     * @return \Deriver\Deriver\Lowerer\Graph\ExpressionRef Argument value reference
     * @throws InvalidInputException If the argument is absent
     */
    public function argument(int|string $name): \Deriver\Deriver\Lowerer\Graph\ExpressionRef
    {
        return $this->arguments[$name] ?? throw new InvalidInputException('Argument is absent at this observation.');
    }

    /**
     * Selects the point after argument evaluation and before entering the callee.
     * @return \Deriver\Deriver\Lowerer\Graph\PointRef Invocation observation
     */
    public function beforeInvocation(): \Deriver\Deriver\Lowerer\Graph\PointRef
    {
        return new \Deriver\Deriver\Lowerer\Graph\PointRef($this->source, $this->callable, $this->instruction, \Deriver\Deriver\Lowerer\Graph\ObservationPhase::Invocation);
    }

    /**
     * Selects memory immediately after normal completion of the call.
     * @return \Deriver\Deriver\Lowerer\Graph\PointRef Post-call observation
     */
    public function afterInvocation(): \Deriver\Deriver\Lowerer\Graph\PointRef
    {
        return new \Deriver\Deriver\Lowerer\Graph\PointRef($this->source, $this->callable, $this->instruction, \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After);
    }
}
