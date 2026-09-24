<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function\Argument;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the order of positional, named and variadic arguments in one call.
 * @visibility SqlSemantics
 */
final class ArgumentOrder
{
    /**
     * Positional arguments precede named ones, parameter names are unique, and only the last argument is variadic.
     *
     * @param list<Expression> $arguments
     * @throws InvalidStructure
     */
    public static function validate(array $arguments): void
    {
        $names = [];
        $last = count($arguments) - 1;
        foreach ($arguments as $position => $argument) {
            if ($argument instanceof VariadicArgument) {
                if ($position !== $last) {
                    throw new InvalidStructure('Only the last argument of a call can be VARIADIC.');
                }
                $argument = $argument->value;
            }
            if ($argument instanceof NamedArgument) {
                if (isset($names[$argument->name])) {
                    throw new InvalidStructure('A call names each parameter at most once.');
                }
                $names[$argument->name] = true;
            } elseif ($names !== []) {
                throw new InvalidStructure('A positional argument cannot follow a named argument.');
            }
        }
    }
}
