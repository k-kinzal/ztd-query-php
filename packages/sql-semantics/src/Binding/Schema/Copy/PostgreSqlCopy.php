<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Copy;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\PostgreSqlTable\PostgreSqlTables;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\CreatePartitionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Schema\Column\SuppliedColumn;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

/**
 * Derives PostgreSQL table declarations whose columns come from other relations: partitions, typed tables, inheritance, and LIKE templates.
 * @visibility SqlSemantics
 */
final class PostgreSqlCopy
{
    /**
     * Declares a partition with its parent's columns and constraints plus its own overrides, or a typed table whose columns stay unresolved because composite types are not part of the schema; returns null for other declarations.
     *
     * @throws SemanticException
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function form(Node $source, TableResolver $resolver): ?TableDefinition
    {
        $statement = PostgreSqlTables::bind(new Origin('declaration', $source, $resolver->schema->dialect), $source, new QueryContext($resolver));
        if ($statement === null) {
            return null;
        }
        $parts = $statement->name->parts;
        $namespace = count($parts) > 1 ? $parts[count($parts) - 2] : $resolver->defaultSchema;
        $name = $parts[count($parts) - 1];
        if (!$statement instanceof CreatePartitionStatement) {
            return new TableDefinition($namespace, $name, [], $statement->constraints, $source, false, properties: $statement->properties);
        }
        $parent = $resolver->resolve($statement->parent->parts, $source);
        $columns = array_map(static fn (ColumnDefinition $column): ColumnDefinition => self::override($column, $statement->columns), $parent->columns);
        $table = new TableDefinition($namespace, $name, $columns, [...$parent->constraints, ...$statement->constraints], $source, $parent->resolved, properties: $statement->properties);
        return DeclarationReferences::rebind($table, $parent);
    }

    /**
     * Applies a partition's NOT NULL, default or generation, and collation override to an inherited column.
     *
     * @param list<PartitionColumn> $overrides
     */
    public static function override(ColumnDefinition $column, array $overrides): ColumnDefinition
    {
        $matches = array_values(array_filter($overrides, static fn (PartitionColumn $override): bool => $override->column === $column->name));
        if ($matches === []) {
            return $column;
        }
        $override = $matches[0];
        $declared = !$override->generation instanceof SuppliedColumn || $override->generation->default !== null;
        return new ColumnDefinition(
            $column->name,
            $column->type,
            $override->nullability === Nullability::NotNull ? Nullability::NotNull : $column->nullability,
            $column->source,
            $declared ? $override->generation : $column->generation,
            $override->collation === null ? $column->attributes : new \SqlSemantics\Schema\Column\Attributes(collation: $override->collation),
        );
    }

    /**
     * Lays out columns in catalog order, inherited parent columns first and then declared columns with each LIKE template expanded where it is written, and returns them with the constraints the declaration receives from its parents and templates; a name already laid out merges with its first occurrence.
     *
     * @param list<ColumnDefinition> $declared
     * @param list<TableConstraint> $constraints
     * @return array{list<ColumnDefinition>, list<TableConstraint>}
     * @throws SemanticException
     */
    public static function layout(Node $source, array $declared, array $constraints, TableResolver $resolver): array
    {
        $columns = [];
        $inherits = Tree::child($source, ['OptInherit']);
        $parents = $inherits === null ? [] : Tree::outer($inherits, ['qualified_name']);
        $names = array_values(array_filter($source->children, static fn ($child): bool => $child instanceof Node && $child->name === 'qualified_name'));
        if (isset($names[1])) {
            $parents[] = $names[1];
        }
        foreach ($parents as $parent) {
            $base = $resolver->resolve($resolver->identifiers->parts($parent), $parent);
            $columns = self::merge($columns, $base->columns);
            array_push($constraints, ...$base->constraints);
        }
        $pending = $declared;
        foreach (Tree::outer($source, ['columnDef', 'TableLikeClause']) as $element) {
            if ($element->name === 'columnDef') {
                $columns = self::merge($columns, array_splice($pending, 0, 1));
                continue;
            }
            $template = Tree::child($element, ['qualified_name']) ?? $element;
            $base = $resolver->resolve($resolver->identifiers->parts($template), $template);
            $columns = self::merge($columns, $base->columns);
            array_push($constraints, ...$base->constraints);
        }
        return [self::merge($columns, $pending), $constraints];
    }

    /**
     * Appends columns whose names are not yet laid out.
     *
     * @param list<ColumnDefinition> $columns
     * @param list<ColumnDefinition> $additions
     * @return list<ColumnDefinition>
     */
    public static function merge(array $columns, array $additions): array
    {
        foreach ($additions as $addition) {
            if (array_filter($columns, static fn (ColumnDefinition $column): bool => $column->name === $addition->name) === []) {
                $columns[] = $addition;
            }
        }
        return $columns;
    }
}
