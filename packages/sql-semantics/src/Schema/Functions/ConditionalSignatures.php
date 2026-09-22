<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Functions;

use SqlSemantics\Dialect;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Registers ordinary conditional functions with replaceable type and NULL contracts.
 * @visibility SqlSemantics
 */
final class ConditionalSignatures
{
    /**
     * PostgreSQL's conditional constructs are language operations rather than function overloads.
     * @return list<FunctionSignature>
     */
    public static function forDialect(Dialect $dialect): array
    {
        if ($dialect === Dialect::PostgreSql) {
            return [];
        }
        $unknown = TypeDescriptor::builtin($dialect, 'unknown');
        $common = (new BuiltinResult('COALESCE', $dialect))->resolve(...);
        $result = [
            new FunctionSignature('coalesce', [$unknown, $unknown], $common, self::coalesce(...), variadic: true, optionalParameters: $dialect === Dialect::MySql ? 1 : 0),
            new FunctionSignature('ifnull', [$unknown, $unknown], $common, self::coalesce(...)),
            new FunctionSignature('nullif', [$unknown, $unknown], static fn (array $types): TypeDescriptor => $types[0], self::nullIf(...)),
        ];
        if ($dialect === Dialect::MySql) {
            $result[] = new FunctionSignature('greatest', [$unknown, $unknown], $common, Nullability::NotNull, nullOnNull: true, variadic: true);
            $result[] = new FunctionSignature('least', [$unknown, $unknown], $common, Nullability::NotNull, nullOnNull: true, variadic: true);
        } else {
            $result[] = new FunctionSignature('min', [$unknown], static fn (array $types): TypeDescriptor => $types[0], Nullability::MaybeNull, aggregate: true);
            $result[] = new FunctionSignature('max', [$unknown], static fn (array $types): TypeDescriptor => $types[0], Nullability::MaybeNull, aggregate: true);
            $result[] = new FunctionSignature('min', [$unknown, $unknown], $common, Nullability::NotNull, nullOnNull: true, variadic: true);
            $result[] = new FunctionSignature('max', [$unknown, $unknown], $common, Nullability::NotNull, nullOnNull: true, variadic: true);
        }
        return $result;
    }

    /**
     * Describes first-non-NULL selection without accepting runtime values.
     * @param list<Nullability> $arguments
     */
    public static function coalesce(array $arguments): Nullability
    {
        if (in_array(Nullability::NotNull, $arguments, true)) {
            return Nullability::NotNull;
        }
        if (in_array(Nullability::Unknown, $arguments, true)) {
            return Nullability::Unknown;
        }
        return in_array(Nullability::MaybeNull, $arguments, true) ? Nullability::MaybeNull : Nullability::AlwaysNull;
    }

    /**
     * Equality can introduce NULL into the first argument's result.
     * @param list<Nullability> $arguments
     */
    public static function nullIf(array $arguments): Nullability
    {
        return ($arguments[0] ?? Nullability::Unknown) === Nullability::AlwaysNull ? Nullability::AlwaysNull : Nullability::MaybeNull;
    }
}
