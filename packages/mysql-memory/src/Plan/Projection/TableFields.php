<?php

declare(strict_types=1);

namespace MySqlMemory\Plan\Projection;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\QueryPlan;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Statement\Shape\Field;

/**
 * Applies a semantic TABLE projection after its relation rows are planned.
 * Existence-context constants are supplied by Semantics, not inferred here.
 * @visibility MySqlMemory
 */
final class TableFields
{
    /**
     * Preserves the ordinary scan, or projects the expressions retained in query facts.
     */
    public function apply(ExplicitTable $query, QueryPlan $input, Compiler $compiler, ?Scope $outer): QueryPlan
    {
        $fields = $compiler->facts->query($query)->projection;
        if ($fields === [] || !$fields[0] instanceof Field || $fields[0]->expression === null) {
            return $input;
        }
        $scope = new Scope($outer);
        $expressions = [];
        $names = [];
        foreach ($fields as $field) {
            \SqlSemantics\Diagnostic\Check::invariant($field instanceof Field && $field->expression !== null, 'A computed TABLE projection retains every expression.');
            $expressions[] = $compiler->names->field($field, $scope);
            $names[] = $field->name->value ?? '';
        }
        return new QueryPlan(new Project($input->root, $expressions), array_map(static fn (Evaluable $expression): \MySqlMemory\Typing\Domain => $expression->domain(), $expressions), $names);
    }
}
