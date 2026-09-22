<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Writes classified type identities and their own applicable parameters.
 * @visibility SqlSemantics
 */
final class TypeDeclaration
{
    /**
     * @throws InvalidStructure
     */
    public static function write(TypeDescriptor $type): Tree
    {
        $identity = $type->identity;
        if ($identity instanceof Identity\BuiltinIdentity) {
            if (in_array($identity, [Identity\BuiltinIdentity::Unknown, Identity\BuiltinIdentity::Dynamic, Identity\BuiltinIdentity::Never, Identity\BuiltinIdentity::Record], true)) {
                throw new InvalidStructure('An inferred result category cannot be used as a declared SQL type.');
            }
            return Build::keyword($identity->value);
        }
        return match (true) {
            $identity instanceof Identity\SqliteDeclaration => Type\TypeNames::writeSqlite($identity),
            $identity instanceof Identity\NamedIdentity => Type\TypeNames::named($identity, $type->dialect),
            $identity instanceof Identity\ArrayStorage => Type\TypeNames::array($identity),
            $identity instanceof Identity\Enumeration, $identity instanceof Identity\LabelSet => Type\TypeNames::labels($identity, $type->dialect),
            $identity instanceof Identity\Numeric\IntegerStorage => Type\TypeParameters::integer($identity),
            $identity instanceof Identity\Numeric\NumericStorage => Type\TypeParameters::numeric($identity),
            $identity instanceof Identity\StringStorage => Type\TypeParameters::string($identity, $type->dialect),
            $identity instanceof Identity\TemporalStorage => Type\TypeParameters::temporal($identity),
            $identity instanceof Identity\IntervalStorage => Type\TypeParameters::interval($identity),
            default => throw new InvalidStructure('Unclassified SQL type identity: ' . $identity::class),
        };
    }
}
