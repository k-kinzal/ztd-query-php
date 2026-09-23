<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\NegatedParameter;
use SqlSemantics\Type\Modifier\TextParameter;

/**
 * Narrows modifier operands for syntax that requires a numeric literal.
 * @visibility SqlSemantics
 */
final class ParameterDomains
{
    /**
     * @throws InvalidSql
     */
    public static function number(NumericParameter|TextParameter|IdentifierParameter|NegatedParameter|null $parameter, Node $source): ?NumericParameter
    {
        if ($parameter !== null && !$parameter instanceof NumericParameter) {
            throw new InvalidSql(InputViolation::TypeModifier, $source);
        }
        return $parameter;
    }

    /**
     * Rejects excess modifiers instead of silently discarding positions.
     * @throws InvalidSql
     */
    public static function arity(BuiltinIdentity $base, int $count, Dialect $dialect, Node $source): void
    {
        $maximum = match ($base) {
            BuiltinIdentity::Numeric => 2,
            BuiltinIdentity::Real, BuiltinIdentity::DoublePrecision => $dialect === Dialect::MySql ? 2 : 0,
            BuiltinIdentity::Float => $dialect === Dialect::MySql ? 2 : 1,
            BuiltinIdentity::TinyInt, BuiltinIdentity::SmallInt, BuiltinIdentity::MediumInt, BuiltinIdentity::Integer, BuiltinIdentity::BigInt, BuiltinIdentity::Year => $dialect === Dialect::MySql ? 1 : 0,
            BuiltinIdentity::Time, BuiltinIdentity::Timestamp, BuiltinIdentity::Datetime, BuiltinIdentity::Timetz, BuiltinIdentity::Timestamptz,
            BuiltinIdentity::Char, BuiltinIdentity::Varchar, BuiltinIdentity::Bit, BuiltinIdentity::Varbit => 1,
            BuiltinIdentity::Text, BuiltinIdentity::TinyText, BuiltinIdentity::MediumText, BuiltinIdentity::LongText,
            BuiltinIdentity::Binary, BuiltinIdentity::Varbinary, BuiltinIdentity::Blob, BuiltinIdentity::TinyBlob, BuiltinIdentity::MediumBlob, BuiltinIdentity::LongBlob, BuiltinIdentity::Vector => $dialect === Dialect::MySql ? 1 : 0,
            default => 0,
        };
        if ($count > $maximum) {
            throw new InvalidSql(InputViolation::TypeParameterCount, $source);
        }
    }

}
