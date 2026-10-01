<?php

declare(strict_types=1);

namespace Deriver\Model\Plan;

use Deriver\Model\Binding\LocationRef;
use Deriver\Value\Term;

/**
 * A pure plan expression referencing parameters, state, or registered operations.
 *
 * @visibility public
 * @example Keeping a symbolic parameter
 *     \Deriver\Model\Plan\Expression::parameter('id')->name // => 'id'
 */
final class Expression
{
    /**
     * @param string $operation Plan expression operation
     * @param string $name Parameter, intrinsic, slot, or operator name
     * @param list<self> $operands Ordered subexpressions
     * @param Term|null $constant Literal value
     * @param LocationRef|null $location Storage read by a location expression
     */
    public function __construct(public readonly string $operation, public readonly string $name = '', public readonly array $operands = [], public readonly ?Term $constant = null, public readonly ?LocationRef $location = null)
    {
    }

    /**
     * Refers to a normalized parameter or a prior action's result.
     * @param string $name Bound parameter name
     * @return self Parameter expression
     */
    public static function parameter(string $name): self
    {
        return new self('parameter', $name);
    }

    /**
     * Embeds an immutable term.
     * @param Term $value Literal or structured input
     * @return self Constant expression
     */
    public static function literal(Term $value): self
    {
        return new self('constant', constant: $value);
    }

    /**
     * Refers to the bound receiver.
     * @return self Receiver expression
     */
    public static function receiver(): self
    {
        return new self('parameter', 'this');
    }

    /**
     * Uses the same PHP binary semantics as a source expression.
     * @param string $operator PHP operator
     * @param self $left Left operand
     * @param self $right Right operand
     * @return self Binary expression
     */
    public static function binary(string $operator, self $left, self $right): self
    {
        return new self('binary', $operator, [$left, $right]);
    }

    /**
     * Reads a namespaced abstract object slot.
     * @param string $slot Domain-qualified slot name
     * @param self|null $receiver Object expression; omitted means this
     * @return self State expression
     */
    public static function state(string $slot, ?self $receiver = null): self
    {
        return new self('state', $slot, [$receiver ?? self::receiver()]);
    }

    /**
     * Reads a parameter, state slot, or array element through core storage semantics.
     * @param LocationRef $location Declarative address
     * @return self Storage read expression
     */
    public static function read(LocationRef $location): self
    {
        return new self('location-read', location: $location);
    }
}
