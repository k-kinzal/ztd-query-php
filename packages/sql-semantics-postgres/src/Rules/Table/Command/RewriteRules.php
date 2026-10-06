<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\PseudoRelations;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Query;

/**
 * Derives and writes CREATE RULE.
 *
 * Rule: PG-REWRITE-RULE-001. The table is resolved (PG-TABLE-TARGET-001)
 * and is the relation fact of the statement; a materialized view, a foreign
 * table or a sequence cannot have rules (PG-RELATION-KIND-001). The WHERE condition sees OLD
 * and NEW (PG-OLD-NEW-001). Each action is derived as a statement whose
 * enclosing scope holds OLD and NEW, reachable with a qualifier only ("Within
 * condition and command, the special table names NEW and OLD can be used to
 * refer to values in the referenced table"); a NOTIFY action has no
 * expression. Source: https://www.postgresql.org/docs/17/sql-createrule.html.
 * Termination: one pass over the actions. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RewriteRules
{
    /**
     * Resolves the table and derives the condition and the actions.
     */
    public function derive(CreateRule $rule, Derivation $derivation): void
    {
        $fact = $derivation->target($rule, (new Targets())->resolve($derivation, $rule->table));
        $kinds = new RelationKinds();
        $kind = $kinds->of($fact);
        if ($kind === RelationKind::MaterializedView) {
            $derivation->report(new KindProblem(KindRule::RulesOnMaterializedView, null));
        } else {
            $kinds->require($derivation, $kind, $rule->table->name, [RelationKind::BaseTable, RelationKind::View], KindRule::RulesOnRelation);
        }
        $pseudo = new PseudoRelations();
        if ($rule->where !== null) {
            (new Conditions())->derive($derivation, $rule->where, $pseudo->scope($derivation, $rule, $fact, false), 'WHERE');
        }
        $scope = $pseudo->scope($derivation, $rule, $fact, true);
        foreach ($rule->actions as $action) {
            if ($action instanceof Query) {
                $derivation->query($action, $scope);
            } else {
                $derivation->member($action);
            }
        }
    }

    /**
     * Writes CREATE RULE.
     */
    public function write(Output $out, CreateRule $rule): void
    {
        $out->keyword('CREATE');
        if ($rule->replace) {
            $out->keyword('OR', 'REPLACE');
        }
        $out->keyword('RULE')->name($rule->name)->keyword('AS', 'ON', $rule->event->value, 'TO');
        (new Spelling())->qualified($out, $rule->table);
        if ($rule->where !== null) {
            $out->keyword('WHERE')->node($rule->where);
        }
        $out->keyword('DO');
        if ($rule->instead !== null) {
            $out->keyword($rule->instead ? 'INSTEAD' : 'ALSO');
        }
        if ($rule->grouped) {
            $out->symbol('(')->list($rule->actions, ';')->symbol(')');
        } elseif ($rule->actions === []) {
            $out->keyword('NOTHING');
        } else {
            $out->list($rule->actions);
        }
    }
}
