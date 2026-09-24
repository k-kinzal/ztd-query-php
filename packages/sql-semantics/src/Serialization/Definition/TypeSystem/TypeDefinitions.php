<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Composite;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes shell, composite, and enum type definitions and their ALTER TYPE forms.
 * @visibility SqlSemantics
 */
final class TypeDefinitions
{
    /**
     * Returns null for statements outside these type forms.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\CreateShellTypeStatement => new Tree('create-type', [Build::keyword('CREATE TYPE'), Build::identifier($statement->name->parts, $dialect)]),
            $statement instanceof Statement\CreateCompositeTypeStatement => new Tree('create-type', [Build::keyword('CREATE TYPE'), Build::identifier($statement->name->parts, $dialect), Build::keyword('AS'), Build::parentheses(Build::separated(array_map(self::attribute(...), $statement->attributes)))]),
            $statement instanceof Statement\CreateEnumTypeStatement => new Tree('create-type', [Build::keyword('CREATE TYPE'), Build::identifier($statement->name->parts, $dialect), Build::keyword('AS ENUM'), Build::parentheses(Build::separated(array_map(self::text(...), $statement->labels)))]),
            $statement instanceof Statement\AddEnumLabelStatement => new Tree('alter-type', [self::alter($statement->type), Build::keyword('ADD VALUE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), self::text($statement->label), ...($statement->position === null ? [] : [Build::keyword($statement->position->placement->value), self::text($statement->position->neighbor)])]),
            $statement instanceof Statement\RenameEnumLabelStatement => new Tree('alter-type', [self::alter($statement->type), Build::keyword('RENAME VALUE'), self::text($statement->label), Build::keyword('TO'), self::text($statement->newLabel)]),
            $statement instanceof Statement\CreateBaseTypeStatement => new Tree('create-type', [Build::keyword('CREATE TYPE'), Build::identifier($statement->name->parts, $dialect), DefinitionLists::write($statement->options)]),
            $statement instanceof Statement\CreateRangeTypeStatement => new Tree('create-type', [Build::keyword('CREATE TYPE'), Build::identifier($statement->name->parts, $dialect), Build::keyword('AS RANGE'), DefinitionLists::write($statement->options)]),
            $statement instanceof Statement\AlterTypeOptionsStatement => new Tree('alter-type', [self::alter($statement->type), Build::keyword('SET'), DefinitionLists::write($statement->options)]),
            $statement instanceof Statement\AlterCompositeTypeStatement => new Tree('alter-type', [self::alter($statement->type), Build::separated(array_map(self::change(...), $statement->changes))]),
            default => null,
        };
    }

    /**
     * Writes an attribute name, its type, and its optional collation.
     * @throws InvalidStructure
     */
    public static function attribute(Composite\CompositeAttribute $attribute): Tree
    {
        return new Tree('attribute', [Build::identifier([$attribute->name], Dialect::PostgreSql), TypeDeclaration::write($attribute->type), ...self::collation($attribute->collation)]);
    }

    /**
     * Writes one ADD, DROP, or ALTER ATTRIBUTE command.
     * @throws InvalidStructure
     */
    public static function change(Composite\AttributeChange $change): Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $change instanceof Composite\AddAttribute => new Tree('add-attribute', [Build::keyword('ADD ATTRIBUTE'), self::attribute($change->attribute), Build::keyword($change->behavior->value)]),
            $change instanceof Composite\DropAttribute => new Tree('drop-attribute', [Build::keyword('DROP ATTRIBUTE' . ($change->ifExists ? ' IF EXISTS' : '')), Build::identifier([$change->name], $dialect), Build::keyword($change->behavior->value)]),
            $change instanceof Composite\RetypeAttribute => new Tree('alter-attribute', [Build::keyword('ALTER ATTRIBUTE'), Build::identifier([$change->name], $dialect), Build::keyword('TYPE'), TypeDeclaration::write($change->type), ...self::collation($change->collation), Build::keyword($change->behavior->value)]),
            default => throw new InvalidStructure('Unclassified composite attribute change: ' . $change::class),
        };
    }

    /**
     * Writes an optional COLLATE clause.
     * @return list<Tree>
     */
    public static function collation(?QualifiedName $collation): array
    {
        return $collation === null ? [] : [Build::keyword('COLLATE'), Build::identifier($collation->parts, Dialect::PostgreSql)];
    }

    /**
     * Encodes decoded text as one standard string constant.
     * @throws InvalidStructure
     */
    public static function text(string $value): Tree
    {
        return new Tree('literal', [new Atom('literal', Literal::encode($value, Dialect::PostgreSql)[0])]);
    }

    /**
     * The command prefix naming the altered type.
     */
    public static function alter(QualifiedName $type): Tree
    {
        return new Tree('alter-type-target', [Build::keyword('ALTER TYPE'), Build::identifier($type->parts, Dialect::PostgreSql)]);
    }
}
