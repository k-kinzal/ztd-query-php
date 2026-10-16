<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Expression\ExpressionRole;
use MySqlMemory\Command\Definition\Expression\ExpressionRules;
use MySqlMemory\Command\Definition\Expression\ItemText;
use MySqlMemory\Command\Definition\Expression\TableExpressions;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

/**
 * Compiles functional key parts into internal row slots with no SQL column identity.
 *
 * The expressions are already resolved by Semantics. Their computed slots let
 * ordinary key comparison enforce uniqueness after inserts and updates. They
 * never enter the table declaration used for name resolution or SELECT stars.
 *
 * @visibility MySqlMemory
 */
final class FunctionalKeys
{
    /**
     * Adds one computed slot per indexed expression, preserving declared column positions.
     *
     * @return list<ColumnDefinition>
     *
     * @throws \MySqlMemory\Error\SqlError When an indexed expression is forbidden or cannot be compiled
     */
    public function columns(CreateTable $create, TableDefinition $definition, Planner $planner): array
    {
        $expressions = new TableExpressions($planner, $create);
        $scope = $expressions->scope($definition);
        $columns = $definition->columns;
        $names = [];
        foreach ($columns as $column) {
            $names[mb_strtolower($column->name)] = $column->name;
        }
        $text = new ItemText($names, $planner->settings->connectionCollation->charset->name, $planner->settings->release());
        $rules = new ExpressionRules();
        foreach ($create->elements as $index) {
            if (!$index instanceof IndexDefinition) {
                continue;
            }
            foreach ($index->parts as $part) {
                if (!$part instanceof ExpressionPart) {
                    continue;
                }
                $this->check($part, $index, $definition->engine);
                $owner = $index->name?->column->value ?? $index->constraint?->name?->column->value ?? 'functional_index';
                $rules->forbidden($part->expression, ExpressionRole::Functional, $owner);
                $rules->grouped($part->expression);
                $this->references($part, $expressions, $definition, $owner);
                $value = $expressions->compiled($part->expression, $scope);
                $this->result($value->domain());
                $columns[] = new ColumnDefinition($this->name($part), $value->domain(), Fill::none(), generated: $value, invisible: true, expression: $text->text($part->expression), functional: $part);
            }
        }

        return $columns;
    }

    /**
     * Identifies a computed slot without allocating a name in the SQL identifier domain.
     */
    public function name(ExpressionPart $part): string
    {
        return "\0index:" . spl_object_id($part);
    }

    /**
     * Tells whether added functional indexes require the observed copying ALTER path.
     *
     * Named non-unique indexes are built in place. Unique indexes and indexes
     * whose name is assigned by the server report the rebuilt row count.
     *
     * @param list<array{\SqlSemantics\Platform\MySql\Statement\Table\TableElement, \MySqlMemory\Dictionary\Key|null}> $indexes The changed layout's index declarations and their old keys
     */
    public function copies(array $indexes): bool
    {
        foreach ($indexes as [$index, $old]) {
            if ($old !== null || !$index instanceof IndexDefinition || ($index->kind !== \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Unique && ($index->name !== null || $index->constraint?->name !== null))) {
                continue;
            }
            foreach ($index->parts as $part) {
                if ($part instanceof ExpressionPart) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks constraints that cannot be changed by the indexed rows.
     *
     * @throws \MySqlMemory\Error\SqlError When the index kind, expression or storage engine is forbidden
     */
    public function check(ExpressionPart $part, IndexDefinition $index, string $engine): void
    {
        if ($index->kind === \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText) {
            throw \MySqlMemory\Error\Family\ConstraintError::FunctionalFullText->error();
        }
        if ($index->kind === \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Spatial) {
            throw \MySqlMemory\Error\Family\ConstraintError::FunctionalSpatial->error();
        }
        if ($index->kind === \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Primary) {
            throw \MySqlMemory\Error\Family\ConstraintError::FunctionalPrimary->error();
        }
        $expression = $part->expression;
        while ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) {
            throw \MySqlMemory\Error\Family\ConstraintError::FunctionalColumn->error();
        }
        if (strcasecmp($engine, 'InnoDB') !== 0) {
            throw \MySqlMemory\Error\Family\SchemaError::IllegalCreateOption->error($engine, 'Index on virtual generated column');
        }
    }

    /**
     * Refuses missing columns and references whose current column is auto-incremented.
     *
     * @throws \MySqlMemory\Error\SqlError When a referenced column is missing or generates its value through AUTO_INCREMENT
     */
    public function references(ExpressionPart $part, TableExpressions $expressions, TableDefinition $definition, string $owner): void
    {
        foreach ($expressions->references($part->expression, $definition->columns) as $reference) {
            if (is_string($reference)) {
                throw \MySqlMemory\Error\Family\QueryError::BadField->error($reference, 'functional index');
            }
            if ($definition->columns[$reference]->autoIncrement) {
                throw \MySqlMemory\Error\Family\ConstraintError::FunctionalAutoIncrement->error($owner);
            }
        }
    }

    /**
     * Refuses result types without an ordinary functional-key representation.
     *
     * @throws \MySqlMemory\Error\SqlError When the expression returns JSON or geometry
     */
    public function result(\MySqlMemory\Typing\Domain $domain): void
    {
        if (in_array($domain->field, [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Json, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Geometry], true)) {
            throw \MySqlMemory\Error\Family\ConstraintError::FunctionalJsonGeometry->error();
        }
    }
}
