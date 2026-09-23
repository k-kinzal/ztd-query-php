<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Enforces PostgreSQL argument-type declarations and compares variadic type requests.
 * @visibility SqlSemantics
 */
final class ArgumentTypeInvariant
{
    /**
     * Rejects inferred value categories and declarations from another SQL dialect.
     * @throws InvalidStructure
     */
    public static function validate(TypeDescriptor|ColumnTypeReference $type): void
    {
        if ($type instanceof TypeDescriptor) {
            if ($type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A PostgreSQL argument requires a PostgreSQL type.');
            }
            TypeDeclaration::write($type);
        }
    }

    /**
     * Compares declared type requests independently of source positions and names of arguments.
     */
    public static function same(TypeDescriptor|ColumnTypeReference $left, TypeDescriptor|ColumnTypeReference $right): bool
    {
        if ($left instanceof ColumnTypeReference || $right instanceof ColumnTypeReference) {
            return $left instanceof ColumnTypeReference && $right instanceof ColumnTypeReference && $left->name->parts === $right->name->parts;
        }
        return TypeDeclaration::write($left)->toString() === TypeDeclaration::write($right)->toString();
    }
}
