<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Value\Term;

/**
 * Validates argument order and maps values by the selected signature's names.
 * @visibility root
 */
final class ArgumentOrder
{
    /**
     * Normalizes one actual argument list for one candidate signature.
     * @param CallableGraph $callable Candidate callable
     * @param list<PassedArgument> $arguments Actual arguments
     * @return array<string, PassedArgument>|string Bound arguments or throwable class
     */
    public function normalize(CallableGraph $callable, array $arguments): array|string
    {
        $result = [];
        $names = [];
        $variadic = null;
        foreach ($callable->parameters as $parameter) {
            $names[] = $parameter->name;
            if ($parameter->variadic) {
                $variadic = $parameter->name;
            }
        }
        $position = 0;
        $named = false;
        $extras = [];
        $elements = [];
        foreach ($arguments as $argument) {
            if ($argument->name === '*') {
                foreach ($names as $name) {
                    $result[$name] ??= new PassedArgument(Term::opaque('UNKNOWN_ARGUMENT_UNPACK'));
                }
                continue;
            }
            if ($argument->name === null && $named) {
                return 'Error';
            }
            $named = $named || $argument->name !== null;
            $name = $argument->name ?? ($names[$position++] ?? '');
            if ($name === $variadic || !in_array($name, $names, true)) {
                $error = $this->extra($callable, $argument, $variadic, $extras, $elements);
                if ($error !== null) {
                    return $error;
                }
            } elseif (isset($result[$name])) {
                return 'Error';
            } else {
                $result[$name] = $argument;
            }
        }
        if ($variadic !== null) {
            $result[$variadic] = new PassedArgument(Term::array($extras), elements: $elements);
        }
        return $result;
    }

    /**
     * Validates and records arguments beyond the declared fixed parameters.
     * @param CallableGraph $callable Selected signature
     * @param PassedArgument $argument Surplus actual
     * @param string|null $variadic Variadic parameter name
     * @param array<int|string, Term> $extras Collected variadic values
     * @param array<int|string, PassedArgument> $elements Collected variadic addresses
     * @return string|null Throwable class if the argument is invalid
     */
    public function extra(CallableGraph $callable, PassedArgument $argument, ?string $variadic, array &$extras, array &$elements): ?string
    {
        if ($variadic === null && $argument->name === null && !$callable->allowExtraArguments) {
            return 'ArgumentCountError';
        }
        if ($variadic === null && $argument->name !== null) {
            return 'Error';
        }
        if ($argument->name === null) {
            $extras[] = $argument->value;
            $elements[] = $argument;
        } else {
            if (isset($extras[$argument->name])) {
                return 'Error';
            }
            $extras[$argument->name] = $argument->value;
            $elements[$argument->name] = $argument;
        }
        return null;
    }
}
