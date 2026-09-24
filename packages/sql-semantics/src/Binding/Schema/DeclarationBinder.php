<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Ast\Declaration\TableDefinition as ParsedTable;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * Turns internal declaration syntax into a graph of typed semantic declarations.
 *
 * @visibility SqlSemantics
 */
final class DeclarationBinder
{
    /**
     * Resolves expressions using symbols so declarations never contain reference cycles.
     * A declaration cannot build a key on an existing index; only ALTER TABLE can.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(ParsedTable $table, QueryContext $context): TableDefinition
    {
        foreach ($table->constraints as $constraint) {
            $existing = \SqlSemantics\Ast\Tree::outer($constraint->source, ['ExistingIndex'])[0] ?? null;
            if ($existing !== null) {
                throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ExistingIndexConstraint, $existing);
            }
        }
        $columns = array_map(static fn ($column): ColumnDefinition => new ColumnDefinition($column->name, $column->type, $column->nullability, $column->source), $table->columns);
        $skeleton = new TableDefinition($table->schema, $table->name, $columns, [], $table->source);
        $target = new TableReference('declaration', 'declaration', $skeleton, new \SqlSemantics\Model\Relation\QualifiedName($skeleton->schema === '' ? [$skeleton->name] : [$skeleton->schema, $skeleton->name]), null, $table->source);
        $scope = new Scope($context->tables->identifiers, [$target], queries: $context);
        $constraints = array_map(static fn ($constraint): \SqlSemantics\Schema\TableConstraint => ConstraintBinder::bind($constraint, $scope), $table->constraints);
        if (count(array_filter($constraints, static fn ($constraint): bool => $constraint instanceof \SqlSemantics\Schema\Constraint\PrimaryKey)) > 1) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::MultiplePrimaryKeys, $table->source);
        }
        return new TableDefinition(
            $table->schema,
            $table->name,
            array_map(static fn ($column): ColumnDefinition => ColumnBinder::bind($column, $scope), $table->columns),
            $constraints,
            $table->source,
            $table->resolved,
            array_map(static fn ($index): \SqlSemantics\Schema\IndexDefinition => IndexBinder::definition($index, $scope), $table->indexes),
            TablePropertiesBinder::bind($table, $scope),
        );
    }
}
