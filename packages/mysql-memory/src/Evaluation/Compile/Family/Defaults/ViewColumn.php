<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family\Defaults;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Evaluation\Compile\Compiler;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

/**
 * Resolves a view output's default through its bound relation occurrence.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class ViewColumn
{
    /**
     * @param Compiler $compiler The active statement's facts and catalog
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Finds a view column through its bound occurrence, whose declaration need not be stored.
     */
    public function column(ResolvedColumn $resolution): ?ColumnDefinition
    {
        $relation = $resolution->relation->hasBinding() ? $resolution->relation->relationInformation()->table : null;
        if (!$relation instanceof \SqlSemantics\Statement\Reference\Table\DeclaredTable || $relation->table->kind !== \SqlSemantics\Statement\Declaration\RelationKind::View) {
            return null;
        }
        $name = $relation->table->name;
        $dictionary = $this->compiler->planner->dictionary;
        $view = $dictionary->schema($name->schema->value ?? $this->compiler->settings->database)->views[$name->name->value] ?? null;
        if ($view === null) {
            return null;
        }
        foreach ($relation->table->columns as $position => $column) {
            if ($column === $resolution->declaration()) {
                $binding = $this->compiler->planner->declarations->view($view);
                return \MySqlMemory\Plan\Views::stored($binding, $dictionary)->definition->columns[$position];
            }
        }
        return null;
    }

}
