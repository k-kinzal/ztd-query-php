<?php

declare(strict_types=1);

namespace Deriver\Model\Plan;

use Deriver\Model\Binding\LocationRef;

/**
 * An ordered state effect, callback invocation, branch, or completion.
 *
 * @visibility public
 * @example Returning a bound input
 *     \Deriver\Model\Plan\Action::returns(\Deriver\Model\Plan\Expression::parameter('id'))->operation // => 'return'
 */
final class Action
{
    /**
     * @param string $operation Action opcode
     * @param list<Expression> $operands Evaluated input expressions
     * @param string $name State slot, call target, or result binding
     * @param list<self> $yes True branch actions
     * @param list<self> $no False branch actions
     * @param list<LocationRef> $locations Ordered storage targets
     * @param list<CallArgument> $arguments Ordered invocation or constructor arguments
     * @param bool $referenceResult Whether to retain a returned reference in the result binding
     * @param bool $mayThrow Whether an explicit havoc may also complete exceptionally
     */
    public function __construct(public readonly string $operation, public readonly array $operands = [], public readonly string $name = '', public readonly array $yes = [], public readonly array $no = [], public readonly array $locations = [], public readonly array $arguments = [], public readonly bool $referenceResult = false, public readonly bool $mayThrow = false)
    {
    }

    /**
     * Completes normally with a value.
     * @param Expression $value Return expression
     * @return self Return action
     */
    public static function returns(Expression $value): self
    {
        return new self('return', [$value]);
    }

    /**
     * Updates one receiver state slot.
     * @param string $slot Namespaced slot
     * @param Expression $value Assigned expression
     * @param Expression|null $receiver Object expression
     * @return self State write
     */
    public static function write(string $slot, Expression $value, ?Expression $receiver = null): self
    {
        return new self('state-write', [$receiver ?? Expression::receiver(), $value], $slot);
    }

    /**
     * Calls a callback synchronously exactly once, preserving effects and exceptions.
     * @param string $result Local binding receiving the callback result
     * @param Expression $callback Callable expression
     * @param list<Expression> $arguments Callback arguments
     * @return self Callback action
     */
    public static function callback(string $result, Expression $callback, array $arguments = []): self
    {
        return new self('callback', [$callback, ...$arguments], $result);
    }

    /**
     * Selects an action sequence using a symbolic guard.
     * @param Expression $condition Branch condition
     * @param list<self> $yes True branch
     * @param list<self> $no False branch
     * @return self Conditional action
     */
    public static function choice(Expression $condition, array $yes, array $no): self
    {
        return new self('choice', [$condition], yes: $yes, no: $no);
    }

    /**
     * Writes a reference parameter, local result, state slot, or array element.
     * @param LocationRef $location Destination
     * @param Expression $value Assigned value
     * @return self Ordered storage update
     */
    public static function assign(LocationRef $location, Expression $value): self
    {
        return new self('location-write', [$value], locations: [$location]);
    }

    /**
     * Rebinds one address to the cell exposed by another address.
     * @param LocationRef $destination Rebound location
     * @param LocationRef $source Shared cell
     * @return self Reference alias action
     */
    public static function alias(LocationRef $destination, LocationRef $source): self
    {
        return new self('alias', locations: [$destination, $source]);
    }

    /**
     * Returns a shared cell; the model signature must declare a reference return.
     * @param LocationRef $location Returned cell
     * @return self Reference completion
     */
    public static function returnReference(LocationRef $location): self
    {
        return new self('return-reference', locations: [$location]);
    }

    /**
     * Propagates a throwable and every state update already completed by the plan.
     * @param Expression $exception Throwable expression
     * @return self Exceptional completion
     */
    public static function throws(Expression $exception): self
    {
        return new self('throw', [$exception]);
    }

    /**
     * Invokes a known or symbolic callable through ordinary core argument binding.
     * @param string $result Local result binding
     * @param Expression $callable Callable expression
     * @param list<CallArgument> $arguments Positional, named, or unpacked arguments
     * @param bool $byReference Whether to preserve a returned reference
     * @return self Synchronous invocation with propagated effects and exceptions
     */
    public static function invoke(string $result, Expression $callable, array $arguments = [], bool $byReference = false): self
    {
        return new self('invoke', [$callable], $result, arguments: $arguments, referenceResult: $byReference);
    }

    /**
     * Constructs an object using the captured class, defaults, and constructor semantics.
     * @param string $result Local object binding
     * @param Expression $class Class name or runtime class expression
     * @param list<CallArgument> $arguments Constructor arguments
     * @return self Allocation and constructor action
     */
    public static function allocate(string $result, Expression $class, array $arguments = []): self
    {
        return new self('allocate', [$class], $result, arguments: $arguments);
    }
    /**
     * Marks explicitly listed storage effects as unresolved, preserving a possible exceptional exit.
     * @param list<LocationRef> $locations Potentially modified storage
     * @param string $reason Explanation of the unavailable behavior
     * @param bool $mayThrow Whether the behavior can throw after the declared effects
     * @return self Conservative state effect and model frontier
     */
    public static function havoc(array $locations, string $reason, bool $mayThrow = true): self
    {
        return new self('havoc', name: $reason, locations: $locations, mayThrow: $mayThrow);
    }
}
