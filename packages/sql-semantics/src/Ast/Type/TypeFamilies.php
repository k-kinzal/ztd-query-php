<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Identity;

/**
 * Constructs each built-in family's applicable parameters.
 * @visibility SqlSemantics
 */
final class TypeFamilies
{
    /**
     * @throws UnclassifiedSql
     */
    public static function make(Identity\BuiltinIdentity $base, TypeWords $parts, Dialect $dialect, Node $source): Identity\TypeIdentity
    {
        $parameters = $parts->parameters;
        $first = $parameters[0] ?? null;
        ParameterDomains::arity($base, count($parameters), $dialect, $source);
        if (in_array($base, [Identity\BuiltinIdentity::TinyInt, Identity\BuiltinIdentity::SmallInt, Identity\BuiltinIdentity::MediumInt, Identity\BuiltinIdentity::Integer, Identity\BuiltinIdentity::BigInt, Identity\BuiltinIdentity::Year], true)) {
            return new Identity\Numeric\IntegerStorage($base, ParameterDomains::number($first, $source), $parts->unsigned);
        }
        if (in_array($base, [Identity\BuiltinIdentity::Numeric, Identity\BuiltinIdentity::Real, Identity\BuiltinIdentity::Float, Identity\BuiltinIdentity::DoublePrecision], true)) {
            return new Identity\Numeric\NumericStorage($base, $first, $parameters[1] ?? null, $parts->unsigned);
        }
        if (in_array($base, [Identity\BuiltinIdentity::Time, Identity\BuiltinIdentity::Timestamp, Identity\BuiltinIdentity::Datetime, Identity\BuiltinIdentity::Timetz, Identity\BuiltinIdentity::Timestamptz], true)) {
            $timezone = str_contains(strtoupper(Tree::text($source)), 'WITHOUT TIME ZONE') ? Identity\TimeZoneMode::Without : (str_contains(strtoupper(Tree::text($source)), 'WITH TIME ZONE') || in_array($base, [Identity\BuiltinIdentity::Timetz, Identity\BuiltinIdentity::Timestamptz], true) ? Identity\TimeZoneMode::With : Identity\TimeZoneMode::Unspecified);
            $base = match ($base) {
                Identity\BuiltinIdentity::Timetz => Identity\BuiltinIdentity::Time, Identity\BuiltinIdentity::Timestamptz => Identity\BuiltinIdentity::Timestamp, Identity\BuiltinIdentity::Time, Identity\BuiltinIdentity::Timestamp, Identity\BuiltinIdentity::Datetime => $base
            };
            return new Identity\TemporalStorage($base, $first, $timezone);
        }
        if (in_array($base, [Identity\BuiltinIdentity::Char, Identity\BuiltinIdentity::Varchar, Identity\BuiltinIdentity::Text, Identity\BuiltinIdentity::TinyText, Identity\BuiltinIdentity::MediumText, Identity\BuiltinIdentity::LongText, Identity\BuiltinIdentity::Binary, Identity\BuiltinIdentity::Varbinary, Identity\BuiltinIdentity::Blob, Identity\BuiltinIdentity::TinyBlob, Identity\BuiltinIdentity::MediumBlob, Identity\BuiltinIdentity::LongBlob, Identity\BuiltinIdentity::Bit, Identity\BuiltinIdentity::Varbit, Identity\BuiltinIdentity::Vector], true)) {
            return new Identity\StringStorage($base, $first, $parts->characterSet, $parts->binary, $parts->national);
        }
        if ($parameters !== [] || $parts->unsigned || $parts->characterSet !== null || $parts->binary) {
            throw new UnclassifiedSql('Unclassified parameters for ' . $base->value . '.');
        }
        return $base;
    }
}
