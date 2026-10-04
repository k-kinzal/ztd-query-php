<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\KeyMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Derives and writes the referenced side of a foreign key.
 *
 * Rule: PG-FOREIGN-KEY-001. The referenced table is resolved by
 * PG-TABLE-TARGET-001 and recorded as the relation facts of the constraint.
 * When its column list is complete, a referenced column it lacks is reported
 * ("column referenced in foreign key constraint does not exist"). At most one
 * ON UPDATE and one ON DELETE clause are written, in either order. MATCH
 * PARTIAL is not implemented by the server, and a SET NULL or SET DEFAULT
 * column list is accepted only for ON DELETE; both are reported.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 * Termination: one pass over the columns and actions. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ForeignKeys
{
    /**
     * Checks the referential actions: at most one per event.
     *
     * @param list<ReferentialAction> $actions
     * @return list<ReferentialAction>
     */
    public function actions(array $actions): array
    {
        $actions = Check::listOf($actions, ReferentialAction::class, 'Referential actions are ON UPDATE and ON DELETE clauses.');
        Check::input(count($actions) < 2 || $actions[0]->event !== $actions[1]->event, 'A foreign key has at most one ON UPDATE and one ON DELETE clause.');
        Check::input(count($actions) <= 2, 'A foreign key has at most one ON UPDATE and one ON DELETE clause.');

        return $actions;
    }

    /**
     * Resolves the referenced table, records it for the constraint and reports what the server rejects.
     *
     * @param list<Name> $columns The referenced columns
     * @param list<ReferentialAction> $actions
     */
    public function derive(Derivation $derivation, Node $constraint, QualifiedName $table, array $columns, ?KeyMatch $match, array $actions): void
    {
        $fact = $derivation->target($constraint, (new Targets())->resolve($derivation, $table));
        if ($fact->table instanceof DeclaredTable) {
            (new KeyColumns())->report($derivation, $columns, $fact->shape, DefinitionRule::MissingReferencedColumn);
        }
        if ($match === KeyMatch::Partial) {
            $derivation->report(new DefinitionProblem(DefinitionRule::MatchPartial));
        }
        foreach ($actions as $action) {
            if ($action->event === ReferenceEvent::Update && $action->columns !== []) {
                $derivation->report(new DefinitionProblem(DefinitionRule::SetColumnsOnUpdate, new Name($action->action->value)));
            }
        }
    }

    /**
     * Writes the referenced table, its columns, the match type and the actions.
     *
     * @param list<Name> $columns
     * @param list<ReferentialAction> $actions
     */
    public function writeTarget(Output $out, QualifiedName $table, array $columns, ?KeyMatch $match, array $actions): void
    {
        (new Spelling())->qualified($out, $table);
        if ($columns !== []) {
            (new Writing())->parenthesized($out, $columns);
        }
        if ($match !== null) {
            $out->keyword('MATCH', $match->value);
        }
        foreach ($actions as $action) {
            $out->node($action);
        }
    }
}
