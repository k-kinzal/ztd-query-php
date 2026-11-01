<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Resolution\Environment;

/**
 * Publishes the attribute a generated column may not carry while retaining every column operand.
 *
 * The operands of a generation expression are a constructor invariant of
 * GeneratedColumn; invocation-time expressions keep their distinct deferred column
 * role.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Conditions
{
    /**
     * Finds the first attribute a generated column may not carry.
     */
    public function problem(M\Table\Column\ColumnDefinition $column): ?M\Table\Problem\Generation\InvalidAttribute
    {
        $specification = $column->specification;
        if (!$specification instanceof M\Table\Column\GeneratedColumn && !$specification instanceof M\Table\Column\Generation\DeferredGeneratedColumn) {
            return null;
        }
        $attribute = (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes())->generated($specification->type, $specification->attributes);
        return $attribute === null ? null : new M\Table\Problem\Generation\InvalidAttribute($column->name->column, $attribute);
    }

    /**
     * Derives operands once, then publishes the attribute condition and its notice.
     */
    public function derive(M\Table\Column\ColumnDefinition $column, Derivation $derivation, Environment $scope): void
    {
        $column->specification->deriveSpecification($derivation, $scope);
        $problem = $this->problem($column);
        if ($problem === null) {
            return;
        }
        $derivation->report($problem);
        $phase = $derivation->context->profile->grammar === GrammarRelease::MySql5744 ? M\Notice\NoticePhase::Syntax : M\Notice\NoticePhase::Contextualization;
        $derivation->warn(new M\Notice\ParseFailure($problem, true, $phase), $column);
    }
}
