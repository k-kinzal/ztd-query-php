<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Scalar;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Type\TypeParameters;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Identity;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Writes the target type of an explicit conversion in the dialect's cast-type vocabulary.
 * @visibility SqlSemantics
 */
final class CastTargets
{
    /**
     * MySQL casts name a narrower set of targets than column declarations; other dialects use declarations.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(TypeDescriptor $type): Tree
    {
        $identity = $type->identity;
        if ($type->dialect !== Dialect::MySql) {
            return TypeDeclaration::write($type);
        }
        return match (true) {
            $identity instanceof Identity\Numeric\IntegerStorage && $identity->base !== Identity\BuiltinIdentity::Year => Build::keyword($identity->unsigned ? 'UNSIGNED' : 'SIGNED'),
            $identity instanceof Identity\Numeric\NumericStorage => self::number($identity),
            $identity instanceof Identity\StringStorage => self::text($identity),
            $identity instanceof Identity\TemporalStorage => new Tree('temporal-type', [Build::keyword(strtoupper($identity->base->value)), TypeParameters::numbers([$identity->precision])]),
            $identity instanceof Identity\BuiltinIdentity && in_array($identity, [Identity\BuiltinIdentity::Integer, Identity\BuiltinIdentity::BigInt], true) => Build::keyword('SIGNED'),
            default => self::keyword($type),
        };
    }

    /**
     * Writes DECIMAL with precision and scale, FLOAT with its precision, and DOUBLE.
     */
    public static function number(Identity\Numeric\NumericStorage $type): Tree
    {
        return match ($type->base) {
            Identity\BuiltinIdentity::Float => new Tree('float-type', [Build::keyword('FLOAT'), TypeParameters::numbers([$type->precision])]),
            Identity\BuiltinIdentity::DoublePrecision, Identity\BuiltinIdentity::Real => Build::keyword('DOUBLE'),
            default => new Tree('decimal-type', [Build::keyword('DECIMAL'), TypeParameters::numbers([$type->precision, $type->scale])]),
        };
    }

    /**
     * Writes BINARY, NCHAR or CHAR with its length, character set and binary collation flag.
     */
    public static function text(Identity\StringStorage $type): Tree
    {
        if (in_array($type->base, [Identity\BuiltinIdentity::Binary, Identity\BuiltinIdentity::Varbinary], true)) {
            return new Tree('binary-type', [Build::keyword('BINARY'), TypeParameters::numbers([$type->length])]);
        }
        return new Tree('char-type', [
            Build::keyword($type->national ? 'NCHAR' : 'CHAR'),
            TypeParameters::numbers([$type->length]),
            ...($type->characterSet === null ? [] : [Build::keyword('CHARACTER SET'), Build::identifier([$type->characterSet], Dialect::MySql)]),
            ...($type->binary ? [Build::keyword('BINARY')] : []),
        ]);
    }

    /**
     * Writes a parameterless target such as DATE, YEAR, JSON or a spatial type by its keyword.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function keyword(TypeDescriptor $type): Tree
    {
        return Build::keyword(strtoupper(TypeDeclaration::write($type)->toString()));
    }
}
