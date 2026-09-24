<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Table;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Definition\Relation\Foreign\TemplateSelection;
use SqlSemantics\Model\Definition\Table\TemplatePlacement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table as Statement;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Serialization\Definition\Constraints;
use SqlSemantics\Serialization\Definition\Relation\ForeignTables;
use SqlSemantics\Serialization\Definition\Relation\PartitionActions;
use SqlSemantics\Serialization\Definition\Storage;

/**
 * Writes the PostgreSQL table declaration forms and the LIKE clauses of an ordinary table declaration.
 * @visibility SqlSemantics
 */
final class PostgreSqlTables
{
    /**
     * Returns null for statements outside the partition and typed table forms.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        if ($statement instanceof Statement\CreatePartitionStatement) {
            return new Tree('create-partition', [
                self::header($statement->properties, $statement->ifNotExists), Build::identifier($statement->name->parts, $dialect),
                Build::keyword('PARTITION OF'), Build::identifier($statement->parent->parts, $dialect),
                ...self::elements($statement->columns, $statement->constraints, $statement->exclusions),
                PartitionActions::bound($statement->bound),
                Storage::table($statement->properties, $dialect),
            ]);
        }
        if ($statement instanceof Statement\CreateTypedTableStatement) {
            return new Tree('create-typed-table', [
                self::header($statement->properties, $statement->ifNotExists), Build::identifier($statement->name->parts, $dialect),
                Build::keyword('OF'), Build::identifier($statement->type->parts, $dialect),
                ...self::elements($statement->columns, $statement->constraints, $statement->exclusions),
                Storage::table($statement->properties, $dialect),
            ]);
        }
        return null;
    }

    /**
     * Writes CREATE TABLE with the persistence keyword and IF NOT EXISTS.
     */
    public static function header(PostgreSqlProperties $properties, bool $ifNotExists): Tree
    {
        $persistence = match ($properties->persistence) {
            Persistence::Permanent => '', Persistence::Temporary => 'TEMPORARY ', Persistence::Unlogged => 'UNLOGGED ',
        };
        return Build::keyword('CREATE ' . $persistence . 'TABLE' . ($ifNotExists ? ' IF NOT EXISTS' : ''));
    }

    /**
     * Writes the parenthesized overrides and constraints, or nothing when there are none.
     *
     * @param list<PartitionColumn> $columns
     * @param list<TableConstraint> $constraints
     * @param list<\SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint> $exclusions
     * @return list<Tree>
     */
    public static function elements(array $columns, array $constraints, array $exclusions): array
    {
        $elements = [
            ...array_map(ForeignTables::column(...), $columns),
            ...array_map(static fn (TableConstraint $constraint): Tree => Constraints::write($constraint, Dialect::PostgreSql), $constraints),
            ...array_map(\SqlSemantics\Serialization\Definition\Relation\ConstraintActions::exclusion(...), $exclusions),
        ];
        return $elements === [] ? [] : [Build::parentheses(Build::separated($elements))];
    }

    /**
     * Writes the LIKE clauses that stand at one position among the declared columns.
     *
     * @param list<TemplatePlacement> $templates
     * @return list<Tree>
     */
    public static function templates(array $templates, int $position): array
    {
        $placed = array_values(array_filter($templates, static fn (TemplatePlacement $template): bool => $template->position === $position));
        return array_map(static fn (TemplatePlacement $placement): Tree => new Tree('table-template', [
            Build::keyword('LIKE'),
            Build::identifier($placement->template->source->parts, Dialect::PostgreSql),
            ...array_map(static fn (TemplateSelection $selection): Tree => Build::keyword(($selection->including ? 'INCLUDING ' : 'EXCLUDING ') . $selection->property->value), $placement->template->selections),
        ]), $placed);
    }
}
