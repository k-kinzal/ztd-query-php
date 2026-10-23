<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Finds defaults through the column declarations retained by semantic slots.
 * A projected base column keeps its default even when its rows are materialized.
 * Computed materialized columns use the temporary column's NULL or zero default.
 * Verified through SQL on MySQL 5.6, 5.7 and 8.4.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Defaults
{
    /**
     * @param Compiler $compiler The statement's resolved facts and catalog
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Resolves a physical default by declaration identity or a temporary default by slot.
     */
    public function column(DefaultOfColumn $node, Scope $scope): ColumnDefinition
    {
        $resolution = $this->compiler->facts->scalar($node->column)->resolution;
        if (!$resolution instanceof ResolvedColumn) {
            throw QueryError::BadField->error($node->column->name->value, 'field list');
        }
        $located = $scope->locate($resolution->relation);
        $holder = $located[1] ?? $scope;
        $id = spl_object_id($resolution->relation);
        if (isset($holder->tables[$id])) {
            return $holder->tables[$id]->columns[$this->compiler->names->position($holder, $resolution)];
        }
        $declaration = $resolution->declaration();
        foreach ($declaration === null ? [] : $this->compiler->planner->dictionary->schemas as $schema) {
            foreach ($schema->tables as $table) {
                foreach ($table->definition->columns as $column) {
                    if ($column->declaration === $declaration) {
                        return $column;
                    }
                }
            }
            foreach ($schema->views as $view) {
                foreach ($view->declaration->columns as $position => $column) {
                    if ($column === $declaration) {
                        return \MySqlMemory\Plan\Views::stored($view, $this->compiler->planner->dictionary)->definition->columns[$position];
                    }
                }
            }
        }
        $position = $this->compiler->names->position($holder, $resolution);
        if (($holder->merged[$id][$position] ?? null) === null) {
            throw DataError::NoDefaultForField->error($node->column->name->value);
        }
        $domain = $holder->columns[$id][$position];
        return new ColumnDefinition($node->column->name->value, $domain, Fill::constant($domain->nullable ? null : \MySqlMemory\Plan\Views::zero($domain), null));
    }

    /**
     * MySQL 5.6 names a materialized alias when its source lacks a default.
     */
    public function name(DefaultOfColumn $node, ColumnDefinition $column): string
    {
        $resolution = $this->compiler->facts->scalar($node->column)->resolution;
        return $this->compiler->settings->release() === GrammarRelease::MySql5651 && $resolution instanceof ResolvedColumn && $resolution->relation instanceof \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable ? $node->column->name->value : $column->name;
    }
}
