<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Publishes definition-time generation conditions while retaining every column operand.
 *
 * Invocation-time expressions keep their distinct deferred column role. Complete
 * declarations publish their contextual dependencies during analysis, so consumers
 * do not analyze the generation expression again to format its refusal.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Conditions
{
    /**
     * Finds the first intrinsic condition of a complete generated column.
     */
    public function problem(M\Table\Column\ColumnDefinition $column, GrammarRelease $release, ?QualifiedName $scope = null): ?Diagnostic
    {
        $specification = $column->specification;
        if (!$specification instanceof M\Table\Column\GeneratedColumn && !$specification instanceof M\Table\Column\Generation\DeferredGeneratedColumn) {
            return null;
        }
        $attribute = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes())->generated($specification->type, $specification->attributes);
        if ($attribute !== null) {
            return new M\Table\Problem\Generation\InvalidAttribute($column->name->column, $attribute);
        }
        $problem = $specification instanceof M\Table\Column\GeneratedColumn ? (new Operands())->problem($specification->expression, $column->name->column, $release) : null;
        return $problem === null ? null : new M\Table\Problem\Generation\RefusedOperand($problem->column, $problem->kind, $problem->function, $scope);
    }

    /**
     * Derives operands once, then publishes their definition-time condition and notice.
     */
    public function derive(M\Table\Column\ColumnDefinition $column, Derivation $derivation, Environment $scope): void
    {
        $before = count($derivation->diagnostics());
        $column->specification->deriveSpecification($derivation, $scope);
        $release = $derivation->context->profile->grammar;
        $target = $scope->relations[0]->name ?? null;
        if ($target !== null && $target->schema === null && $scope->context->searchPath[0]->value !== '') {
            $target = new QualifiedName($target->name, $scope->context->searchPath[0], $target->catalog);
        }
        $problem = $this->problem($column, $release, $target);
        if ($problem === null) {
            return;
        }
        $derivation->report($problem);
        $condition = $problem;
        foreach (array_slice($derivation->diagnostics(), $before) as $dependency) {
            if ($dependency instanceof M\Variable\Problem\UnknownSystemVariable || $dependency instanceof M\Variable\Problem\VariableMisuse) {
                $condition = $dependency;
                break;
            }
        }
        $phase = $release === GrammarRelease::MySql5744 ? M\Notice\NoticePhase::Syntax : M\Notice\NoticePhase::Contextualization;
        $derivation->warn(new M\Notice\ParseFailure($condition, $condition instanceof M\Table\Problem\Generation\InvalidAttribute, $phase), $column);
    }
}
