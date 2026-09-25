<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema\Copy;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Model\Definition\Relation\Foreign\TemplateProperty;
use SqlSemantics\Schema\Column;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Schema\TableDefinition;

/**
 * Copies what a PostgreSQL LIKE clause includes of its template: column names, types, NOT NULL and collations always;
 * defaults (a serial column's nextval default among them), generation expressions, identities, CHECK constraints,
 * keys and indexes, storage modes, compression methods and comments only when their INCLUDING option, or INCLUDING
 * ALL, holds after the options written later; foreign keys never. Copied keys and indexes take names of the new table.
 *
 * @visibility SqlSemantics
 */
final class TemplateOptions
{
    /**
     * Returns the properties the LIKE clause includes, applying its INCLUDING and EXCLUDING options in SQL order.
     *
     * @return list<TemplateProperty>
     * @throws \SqlSemantics\InvalidSql
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function included(Node $like, TableResolver $resolver): array
    {
        $included = [];
        foreach (\SqlSemantics\Binding\Statement\Definition\Relation\ForeignTables::template($like, new QueryContext($resolver))->selections as $selection) {
            foreach ($selection->property === TemplateProperty::All ? TemplateProperty::cases() : [$selection->property] as $property) {
                $included[$property->value] = $selection->including;
            }
        }
        return array_values(array_filter(TemplateProperty::cases(), static fn (TemplateProperty $property): bool => $included[$property->value] ?? false));
    }

    /**
     * Returns a template column as the LIKE clause copies it.
     *
     * @param list<TemplateProperty> $included
     */
    public static function column(ColumnDefinition $column, array $included, TableDefinition $template, TableResolver $resolver): ColumnDefinition
    {
        $has = static fn (TemplateProperty $property): bool => in_array($property, $included, true);
        $generation = $column->generation;
        $generation = match (true) {
            $generation instanceof Column\SuppliedColumn => new Column\SuppliedColumn($has(TemplateProperty::Defaults) ? $generation->default : null),
            $generation instanceof Column\SerialColumn => $has(TemplateProperty::Defaults) ? self::sequenceDefault($column, $template, $resolver) : new Column\SuppliedColumn(),
            $generation instanceof Column\IdentityColumn => $has(TemplateProperty::Identity) ? $generation : new Column\SuppliedColumn(),
            $generation instanceof Column\ComputedColumn => $has(TemplateProperty::Generated) ? $generation : new Column\SuppliedColumn(),
            default => $generation,
        };
        $attributes = $column->attributes;
        $attributes = new Column\Attributes(
            collation: $attributes->collation,
            comment: $has(TemplateProperty::Comments) ? $attributes->comment : null,
            compression: $has(TemplateProperty::Compression) ? $attributes->compression : null,
            storageStrategy: $has(TemplateProperty::Storage) ? $attributes->storageStrategy : null,
        );
        return new ColumnDefinition($column->name, $column->type, $column->nullability, $column->source, $generation, $attributes);
    }

    /**
     * Returns the template constraints the LIKE clause copies: every CHECK with its name for INCLUDING CONSTRAINTS,
     * and the primary and unique keys unnamed for INCLUDING INDEXES.
     *
     * @param list<TableConstraint> $constraints
     * @param list<TemplateProperty> $included
     * @return list<TableConstraint>
     */
    public static function constraints(array $constraints, array $included): array
    {
        $result = [];
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Constraint\Check && in_array(TemplateProperty::Constraints, $included, true)) {
                $result[] = $constraint;
            } elseif (($constraint instanceof Constraint\PrimaryKey || $constraint instanceof Constraint\UniqueKey) && in_array(TemplateProperty::Indexes, $included, true)) {
                $result[] = \SqlSemantics\Binding\Schema\Constraint\PostgreSqlConstraintNames::named($constraint, null);
            }
        }
        return $result;
    }

    /**
     * Returns the default a serial column's copy takes: nextval of the template column's sequence, which the copy
     * does not own.
     */
    public static function sequenceDefault(ColumnDefinition $column, TableDefinition $template, TableResolver $resolver): Column\Generation
    {
        $sequence = \SqlSemantics\Binding\Schema\Constraint\PostgreSqlConstraintNames::objectName($template->name, $column->name, 'seq');
        $parts = $template->schema === $resolver->defaultSchema ? [$sequence] : [$template->schema, $sequence];
        $name = implode('.', array_map(static fn (string $part): string => preg_match('/^[a-z_][a-z0-9_$]*$/D', $part) === 1 ? $part : '"' . str_replace('"', '""', $part) . '"', $parts));
        $tree = (new \SqlSemantics\Ast\DialectParser(\SqlSemantics\Dialect::PostgreSql, $resolver->schema->grammarVersion))->parse("SELECT nextval('" . str_replace("'", "''", $name) . "'::regclass)");
        $value = Tree::outer($tree, ['a_expr'])[0] ?? null;
        return $value === null ? new Column\SerialColumn() : new Column\SuppliedColumn((new \SqlSemantics\Binding\ExpressionBinder())->bind($value, new \SqlSemantics\Binding\Scope($resolver->identifiers, queries: new QueryContext($resolver))));
    }
}
