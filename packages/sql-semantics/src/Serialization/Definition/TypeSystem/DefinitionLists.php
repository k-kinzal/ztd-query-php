<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes definition lists as ATTRIBUTE = argument elements.
 * @visibility SqlSemantics
 */
final class DefinitionLists
{
    /**
     * The parenthesized, comma-separated elements.
     * @param list<DefinitionOption> $options
     * @throws InvalidStructure
     */
    public static function write(array $options): Tree
    {
        return Build::parentheses(Build::separated(array_map(self::option(...), $options)));
    }

    /**
     * One element; a null argument is NONE.
     * @throws InvalidStructure
     */
    public static function option(DefinitionOption $option): Tree
    {
        $value = $option->value;
        return new Tree('definition-element', [Build::keyword($option->attribute->spelling()), Build::keyword('='), match (true) {
            $value === null => Build::keyword('NONE'),
            is_bool($value) => Build::keyword($value ? 'TRUE' : 'FALSE'),
            is_int($value) => Build::keyword((string) $value),
            is_string($value) => TypeDefinitions::text($value),
            $value instanceof QualifiedName => $option->attribute->kind() === DefinitionKind::Operator ? self::operator($value) : Build::identifier($value->parts, Dialect::PostgreSql),
            $value instanceof ColumnTypeReference => new Tree('column-type', [Build::identifier($value->name->parts, Dialect::PostgreSql), Build::keyword('%TYPE')]),
            default => TypeDeclaration::write($value),
        }]);
    }

    /**
     * A bare symbol, or OPERATOR(schema.symbol) when qualified.
     */
    public static function operator(QualifiedName $name): Tree
    {
        return count($name->parts) === 1 ? Build::keyword($name->parts[0]) : new Tree('qualified-operator', [Build::keyword('OPERATOR'), Build::parentheses(ObjectAddresses::operator($name))]);
    }
}
