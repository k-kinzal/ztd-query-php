<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Functions;

use Closure;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Validates function declarations before they become visible to a binder.
 *
 * @visibility SqlSemantics
 */
final class SignatureInvariant
{
    /**
     * @template TParameter
     * @param array<array-key, TParameter>|null $parameters
     * @param TypeDescriptor|Closure(list<TypeDescriptor>): TypeDescriptor $returnType
     * @throws InvalidStructure
     */
    public static function check(string $name, ?array $parameters, TypeDescriptor|Closure $returnType, bool $variadic, int $optionalParameters): void
    {
        if ($name === '' || $optionalParameters < 0 || $optionalParameters > count($parameters ?? []) || ($variadic && ($parameters === null || $parameters === []))) {
            throw new InvalidStructure('A function requires a name and a consistent parameter list.');
        }
        $dialect = $returnType instanceof TypeDescriptor ? $returnType->dialect : null;
        foreach ($parameters ?? [] as $parameter) {
            if (!$parameter instanceof TypeDescriptor || ($dialect !== null && $parameter->dialect !== $dialect)) {
                throw new InvalidStructure('Function argument and result types must belong to one dialect.');
            }
            $dialect ??= $parameter->dialect;
        }
        if ($parameters !== null && !array_is_list($parameters)) {
            throw new InvalidStructure('Function parameters must be an ordered list.');
        }
    }
}
