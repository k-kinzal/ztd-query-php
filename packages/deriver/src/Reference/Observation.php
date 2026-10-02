<?php

declare(strict_types=1);

namespace Deriver\Reference;

use Deriver\Exception\InvalidInputException;

/**
 * A source call or object creation site with references to its once-evaluated argument values.
 *
 * @visibility public
 * @example Constructing an invocation point
 *     $source = new \Deriver\Reference\SourceRef('s', 'a.php', 0, 1);
 *     $observation = new \Deriver\Reference\Observation($source, 'run', 'i1', 'sink', []);
 *     $observation->beforeInvocation()->phase // => 'invocation'
 */
final class Observation
{
    /**
     * @param SourceRef $source Call expression range
     * @param string $callable Owning callable
     * @param string $instruction Call instruction identity
     * @param string $target Statically known function or method name, or the created class for `new`
     * @param array<int|string, ExpressionRef> $arguments Original argument expression references
     * @param ExpressionRef|null $returned Return expression reference; the created object for `new`
     * @param ExpressionRef|null $receiver Evaluated object or class operand for a method call; null for functions and `new`
     * @param string $operation Invocation kind: invoke, invoke-method, invoke-static, or new
     */
    public function __construct(public readonly SourceRef $source, public readonly string $callable, public readonly string $instruction, public readonly string $target, public readonly array $arguments, public readonly ?ExpressionRef $returned = null, public readonly ?ExpressionRef $receiver = null, public readonly string $operation = 'invoke')
    {
    }

    /**
     * Refers to an evaluated argument, not a re-executed expression.
     * @param int|string $name Original argument index or supplied name
     * @return ExpressionRef Argument value reference
     * @throws InvalidInputException If the argument is absent
     */
    public function argument(int|string $name): ExpressionRef
    {
        return $this->arguments[$name] ?? throw new InvalidInputException('Argument is absent at this observation.');
    }

    /**
     * Selects the point after argument evaluation and before entering the callee.
     * @return PointRef Invocation observation
     */
    public function beforeInvocation(): PointRef
    {
        return new PointRef($this->source, $this->callable, $this->instruction, 'invocation');
    }

    /**
     * Selects memory immediately after normal completion of the call.
     * @return PointRef Post-call observation
     */
    public function afterInvocation(): PointRef
    {
        return new PointRef($this->source, $this->callable, $this->instruction, 'after');
    }
}
