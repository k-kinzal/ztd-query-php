<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Indirection;
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
 * of the events, and, in a BEFORE trigger, a reference to a system column of
 * NEW, to a generated column of NEW, or to the whole row of NEW when the
 * table has a generated column are errors ("BEFORE trigger's WHEN condition
 * cannot reference NEW generated columns", with a detail naming the column
 * or the whole-row reference). The first broken rule, in the order the
 * references are written, is reported. A reference counts when it is known
 * to resolve: `old.*`, `new.*`, a whole-row `old` or `new` that is not a
 * column name, `old.c` or `new.c` for a column or system column `c` of a
 * table whose shape is complete, and a field selection `(new).c` of such a
 * column, which the server reads as the column. Terminates: one pass over
 * the finite value graph of the condition.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html,
 * https://www.postgresql.org/docs/16/ddl-generated-columns.html,
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
        foreach ($this->references($when, $fact) as [$old, $system, $generated]) {
            $rule = match (true) {
                !$row => DefinitionRule::StatementTriggerColumns,
                $old && in_array(TriggerEventKind::Insert, $kinds, true) => DefinitionRule::InsertTriggerOld,
                !$old && in_array(TriggerEventKind::Delete, $kinds, true) => DefinitionRule::DeleteTriggerNew,
                !$old && $system && $before => DefinitionRule::BeforeTriggerSystemColumn,
                !$old && $generated && $before => DefinitionRule::BeforeTriggerGeneratedColumn,
                default => null,
            };
            if ($rule !== null) {
                $derivation->report(new DefinitionProblem($rule));

                return;
            }
        }
    }

    /**
     * Answers, in written order, whether each reference of the condition to OLD or NEW is to OLD (else NEW), to a system column, and to a generated column or a whole row holding one.
     *
     * The value graph lists the nodes of the condition depth first from the
     * last member, so its reverse lists the references in written order; a
     * field selection of a whole-row reference counts once, as the column it
     * selects.
     *
     * @return list<array{bool, bool, bool}>
     */
    public function references(Scalar $when, RelationFact $fact): array
    {
        $objects = array_reverse((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Platform\\PostgreSql\\Statement\\']))->objects($when));
        $selected = [];
        foreach ($objects as $object) {
            $base = $object instanceof Indirection && $this->selected($object) !== null ? $object->base : null;
            while ($base instanceof Grouped) {
                $base = $base->operand;
            }
            if ($base !== null) {
                $selected[] = $base;
            }
        }
        $references = [];
        foreach ($objects as $object) {
            $reference = in_array($object, $selected, true) ? null : $this->reference($this->selected($object) ?? $object, $fact);
            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * Answers the column reference a field selection of a whole-row OLD or NEW stands for, `(new).c` as `new.c`, or null for any other node.
     */
    public function selected(object $node): ?ColumnReference
    {
        if (!$node instanceof Indirection || !$node->steps[0] instanceof FieldSelection) {
            return null;
        }
        $base = $node->base;
        while ($base instanceof Grouped) {
            $base = $base->operand;
        }
        if (!$base instanceof ColumnReference || count($base->parts) !== 1 || !in_array($base->parts[0]->value, ['old', 'new'], true)) {
            return null;
        }

        return new ColumnReference([$base->parts[0], $node->steps[0]->name]);
    }

    /**
     * Answers whether a node of the condition refers to OLD (else NEW), to a system column, and to a generated column or a whole row holding one, or null when it refers to neither or may not resolve.
     *
     * @return array{bool, bool, bool}|null
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
        $columns = [];
        $generated = [];
        foreach ($fact->shape->slots as $slot) {
            $columns[] = $slot->name?->value;
            if ($slot->declaration()?->generated === true) {
                $generated[] = $slot->name?->value;
            }
        }
        $system = [];
        foreach ((new Targets())->implicit($fact) as $implicit) {
            array_push($system, ...array_map(static fn ($name): string => $name->value, $implicit->names));
        }
        if ($node instanceof ColumnStar || count($parts) === 1) {
            return $node instanceof ColumnStar || !in_array($pseudo, $columns, true) ? [$pseudo === 'old', false, $generated !== []] : null;
        }
        $column = $parts[1]->value;
        if (in_array($column, $columns, true)) {
            return [$pseudo === 'old', false, in_array($column, $generated, true)];
        }

        return in_array($column, $system, true) ? [$pseudo === 'old', true, false] : null;
    }
}
