<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use Closure;
use InvalidArgumentException;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A function overload, including argument, result, and NULL behavior.
 *
 * @example Registering a typed function
 *     $type = new \SqlSemantics\Type\TypeDescriptor(\SqlSemantics\Dialect::PostgreSql, 'integer');
 *     $function = new \SqlSemantics\Schema\FunctionSignature('twice', [$type], $type);
 *     $function->name // => 'twice'
 *
 * @visibility public
 */
final class FunctionSignature
{
    /**
     * @param string $name Resolved function name without SQL quoting
     * @param list<TypeDescriptor>|null $parameters Argument types; null leaves argument types and arity unspecified
     * @param TypeDescriptor|Closure(list<TypeDescriptor>): TypeDescriptor $returnType Fixed or argument-dependent result type
     * @param Nullability $nullability Result NULL fact when no argument is NULL
     * @param bool $nullOnNull Whether a NULL argument guarantees a NULL result
     * @param bool $variadic Whether the final parameter repeats
     * @param int $optionalParameters Number of optional trailing parameters, including a variadic parameter
     * @param bool $aggregate Whether the function aggregates input rows
     * @param string|null $schema Resolved namespace; null makes the overload available without qualification
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly string $name,
        public readonly ?array $parameters,
        public readonly TypeDescriptor|Closure $returnType,
        public readonly Nullability $nullability = Nullability::Unknown,
        public readonly bool $nullOnNull = false,
        public readonly bool $variadic = false,
        public readonly int $optionalParameters = 0,
        public readonly bool $aggregate = false,
        public readonly ?string $schema = null,
    ) {
        Functions\SignatureInvariant::check($name, $parameters, $returnType, $variadic, $optionalParameters);
    }
}
