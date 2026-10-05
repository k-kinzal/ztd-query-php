<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEvent;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEventKind;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Validation\ValueGraph;

/**
 * Reports the references to OLD and NEW that a trigger's WHEN condition may not make.
 *
 * Rule: PG-TRIGGER-WHEN-001. The server parses the condition with OLD and
 * NEW in scope for every trigger, then checks each column reference of the
 * result in order (CreateTriggerFiringOn, trigger.c): a reference to OLD or
 * NEW in a statement trigger is an error; in a row trigger, a reference to
 * OLD when INSERT is one of the events, a reference to NEW when DELETE is one
 * of the events, and a reference to a system column of NEW in a BEFORE
 * trigger are errors. The first broken rule is reported. A reference counts
 * when it is known to resolve: `old.*`, `new.*`, a whole-row `old` or `new`
 * that is not a column name, and `old.c` or `new.c` for a column or system
 * column `c` of a table whose shape is complete. Terminates: one pass over
 * the finite value graph of the condition.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/commands/trigger.c. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TriggerReferences
{
    /**
     * Reports the first reference of the condition that the trigger may not make.
     *
     * @param list<TriggerEvent> $events The events the trigger fires on
     * @param bool $before Whether the trigger fires BEFORE the event
     */
    public function check(Derivation $derivation, Scalar $when, RelationFact $fact, bool $row, array $events, bool $before): void
    {
        $kinds = array_map(static fn (TriggerEvent $event): TriggerEventKind => $event->kind, $events);
        foreach ((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Platform\\PostgreSql\\Statement\\']))->objects($when) as $object) {
            $reference = $this->reference($object, $fact);
            if ($reference === null) {
                continue;
            }
            [$old, $system] = $reference;
            $rule = match (true) {
                !$row => DefinitionRule::StatementTriggerColumns,
                $old && in_array(TriggerEventKind::Insert, $kinds, true) => DefinitionRule::InsertTriggerOld,
                !$old && in_array(TriggerEventKind::Delete, $kinds, true) => DefinitionRule::DeleteTriggerNew,
                !$old && $system && $before => DefinitionRule::BeforeTriggerSystemColumn,
                default => null,
            };
            if ($rule !== null) {
                $derivation->report(new DefinitionProblem($rule));

                return;
            }
        }
    }

    /**
     * Answers whether a node of the condition refers to OLD (else NEW) and to a system column, or null when it refers to neither or may not resolve.
     *
     * @return array{bool, bool}|null
     */
    public function reference(object $node, RelationFact $fact): ?array
    {
        $parts = match (true) {
            $node instanceof ColumnReference => $node->parts,
            $node instanceof ColumnStar && count($node->qualifiers) === 1 => $node->qualifiers,
            default => [],
        };
        $pseudo = $parts === [] ? null : $parts[0]->value;
        if ($pseudo !== 'old' && $pseudo !== 'new' || !$fact->shape->complete()) {
            return null;
        }
        $columns = array_map(static fn ($slot): ?string => $slot->name?->value, $fact->shape->slots);
        $system = [];
        foreach ((new Targets())->implicit($fact) as $implicit) {
            array_push($system, ...array_map(static fn ($name): string => $name->value, $implicit->names));
        }
        if ($node instanceof ColumnStar || count($parts) === 1) {
            return $node instanceof ColumnStar || !in_array($pseudo, $columns, true) ? [$pseudo === 'old', false] : null;
        }
        $column = $parts[1]->value;
        if (in_array($column, $columns, true)) {
            return [$pseudo === 'old', false];
        }

        return in_array($column, $system, true) ? [$pseudo === 'old', true] : null;
    }
}
