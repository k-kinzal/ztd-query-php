<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\AlterEventTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateConstraintTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateEventTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\EventFilter;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\FiringState;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEvent;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEventKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerTiming;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerTransition;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * Lowers triggers and event triggers.
 *
 * Rule: PG-TRIGGER-LOWER-001. Scope: `CreateTrigStmt`, `TriggerActionTime`,
 * `TriggerEvents`, `TriggerOneEvent`, `TriggerReferencing`,
 * `TriggerTransitions`, `TriggerTransition`, `TransitionOldOrNew`,
 * `TransitionRowOrTable`, `TransitionRelName`, `TriggerForSpec`,
 * `TriggerForOptEach`, `TriggerForType`, `TriggerWhen`, `TriggerFuncArgs`,
 * `TriggerFuncArg`, `OptConstrFromTable`, `FUNCTION_or_PROCEDURE`,
 * `CreateEventTrigStmt`, `event_trigger_when_list`, `event_trigger_when_item`,
 * `event_trigger_value_list`, `AlterEventTrigStmt`, `enable_trigger`. EACH and
 * the choice of FUNCTION or PROCEDURE are noise (TableNoise). Termination:
 * lists are flattened iteratively. Source:
 * https://www.postgresql.org/docs/17/sql-createtrigger.html,
 * https://www.postgresql.org/docs/17/sql-createeventtrigger.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TriggerRule
{
    /**
     * The event each `TriggerOneEvent` production without columns writes.
     */
    private const EVENTS = [
        'TriggerOneEvent: INSERT' => TriggerEventKind::Insert, 'TriggerOneEvent: DELETE_P' => TriggerEventKind::Delete,
        'TriggerOneEvent: UPDATE' => TriggerEventKind::Update, 'TriggerOneEvent: TRUNCATE' => TriggerEventKind::Truncate,
    ];

    /**
     * The state each `enable_trigger` production writes.
     */
    private const STATES = [
        'enable_trigger: ENABLE_P' => FiringState::Enable, 'enable_trigger: ENABLE_P REPLICA' => FiringState::EnableReplica,
        'enable_trigger: ENABLE_P ALWAYS' => FiringState::EnableAlways, 'enable_trigger: DISABLE_P' => FiringState::Disable,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreateTrigStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function trigger(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        $replace = $this->lowering->flags->present($form->node(1));
        if ($form->signature === 'CreateTrigStmt: CREATE opt_or_replace CONSTRAINT TRIGGER name AFTER TriggerEvents ON qualified_name OptConstrFromTable ConstraintAttributeSpec FOR EACH ROW TriggerWhen EXECUTE FUNCTION_or_PROCEDURE func_name ( TriggerFuncArgs )') {
            $from = $this->lowering->productions->form($form->node(9));
            $this->noise($form->node(16), 'FUNCTION_or_PROCEDURE: FUNCTION', 'FUNCTION_or_PROCEDURE: PROCEDURE');

            return new CreateConstraintTrigger(
                $names->name($form->node(4)),
                $this->events($form->node(6)),
                $names->qualified($form->node(8)),
                $names->dotted($form->node(17)),
                $this->arguments($form->node(19)),
                match ($from->signature) {
                    'OptConstrFromTable: FROM qualified_name' => new ParentTable($names->qualified($from->node(1))),
                    'OptConstrFromTable:' => null,
                    default => throw ImplementationGap::production($from),
                },
                $this->lowering->tables->constraintAttributes($form->node(10)),
                $this->when($form->node(14)),
                $replace,
            );
        }
        if ($form->signature !== 'CreateTrigStmt: CREATE opt_or_replace TRIGGER name TriggerActionTime TriggerEvents ON qualified_name TriggerReferencing TriggerForSpec TriggerWhen EXECUTE FUNCTION_or_PROCEDURE func_name ( TriggerFuncArgs )') {
            throw ImplementationGap::production($form);
        }
        $this->noise($form->node(12), 'FUNCTION_or_PROCEDURE: FUNCTION', 'FUNCTION_or_PROCEDURE: PROCEDURE');

        return new CreateTrigger(
            $names->name($form->node(3)),
            $this->timing($form->node(4)),
            $this->events($form->node(5)),
            $names->qualified($form->node(7)),
            $names->dotted($form->node(13)),
            $this->arguments($form->node(15)),
            $this->level($form->node(9)),
            $this->transitions($form->node(8)),
            $this->when($form->node(10)),
            $replace,
        );
    }

    /**
     * Lowers `TriggerActionTime`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function timing(Node $time): TriggerTiming
    {
        $form = $this->lowering->productions->form($time);

        return match ($form->signature) {
            'TriggerActionTime: BEFORE' => TriggerTiming::Before,
            'TriggerActionTime: AFTER' => TriggerTiming::After,
            'TriggerActionTime: INSTEAD OF' => TriggerTiming::InsteadOf,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `TriggerEvents`.
     *
     * @return list<TriggerEvent>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function events(Node $list): array
    {
        $events = [];
        foreach ($this->lowering->items($list, 'TriggerEvents: TriggerOneEvent', 'TriggerEvents: TriggerEvents OR TriggerOneEvent') as $item) {
            $form = $this->lowering->productions->form($item);
            $events[] = $form->signature === 'TriggerOneEvent: UPDATE OF columnList'
                ? new TriggerEvent(TriggerEventKind::Update, $this->lowering->names->names($form->node(2)))
                : new TriggerEvent(self::EVENTS[$form->signature] ?? throw ImplementationGap::production($form));
        }

        return $events;
    }

    /**
     * Lowers `TriggerReferencing`.
     *
     * @return list<TriggerTransition>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function transitions(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'TriggerReferencing:') {
            return [];
        }
        if ($form->signature !== 'TriggerReferencing: REFERENCING TriggerTransitions') {
            throw ImplementationGap::production($form);
        }
        $transitions = [];
        foreach ($this->lowering->items($form->node(1), 'TriggerTransitions: TriggerTransition', 'TriggerTransitions: TriggerTransitions TriggerTransition') as $item) {
            $transition = $this->lowering->productions->form($item);
            $which = $this->lowering->productions->form($transition->node(0));
            $kind = $this->lowering->productions->form($transition->node(1));
            $name = $this->lowering->productions->form($transition->node(3));
            $known = $transition->signature === 'TriggerTransition: TransitionOldOrNew TransitionRowOrTable opt_as TransitionRelName'
                && in_array($which->signature, ['TransitionOldOrNew: NEW', 'TransitionOldOrNew: OLD'], true)
                && in_array($kind->signature, ['TransitionRowOrTable: TABLE', 'TransitionRowOrTable: ROW'], true)
                && $name->signature === 'TransitionRelName: ColId';
            if (!$known) {
                throw ImplementationGap::production($transition);
            }
            $transitions[] = new TriggerTransition($which->signature === 'TransitionOldOrNew: NEW', $kind->signature === 'TransitionRowOrTable: TABLE', $this->lowering->names->name($name->node(0)));
        }

        return $transitions;
    }

    /**
     * Lowers `TriggerForSpec`: true for ROW, false for STATEMENT, null when not written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function level(Node $clause): ?bool
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'TriggerForSpec:') {
            return null;
        }
        $each = $this->lowering->productions->form($form->node(1));
        $type = $this->lowering->productions->form($form->node(2));
        if ($form->signature !== 'TriggerForSpec: FOR TriggerForOptEach TriggerForType' || !in_array($each->signature, ['TriggerForOptEach: EACH', 'TriggerForOptEach:'], true)) {
            throw ImplementationGap::production($form);
        }

        return match ($type->signature) {
            'TriggerForType: ROW' => true,
            'TriggerForType: STATEMENT' => false,
            default => throw ImplementationGap::production($type),
        };
    }

    /**
     * Lowers `TriggerWhen`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function when(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'TriggerWhen: WHEN ( a_expr )' => $this->lowering->expressions->expression($form->node(2)),
            'TriggerWhen:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `TriggerFuncArgs`.
     *
     * @return list<TriggerArgument>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function arguments(Node $list): array
    {
        $arguments = [];
        foreach ($this->lowering->items($list, 'TriggerFuncArgs: TriggerFuncArg', 'TriggerFuncArgs: TriggerFuncArgs , TriggerFuncArg', 'TriggerFuncArgs:') as $item) {
            $form = $this->lowering->productions->form($item);
            $arguments[] = new TriggerArgument(match ($form->signature) {
                'TriggerFuncArg: Iconst' => $this->lowering->literals->integer($form->node(0)),
                'TriggerFuncArg: FCONST' => $this->lowering->literals->numberToken($form->token(0)),
                'TriggerFuncArg: Sconst' => $this->lowering->literals->string($form->node(0)),
                'TriggerFuncArg: ColLabel' => $this->lowering->names->name($form->node(0)),
                default => throw ImplementationGap::production($form),
            });
        }

        return $arguments;
    }

    /**
     * Lowers `CreateEventTrigStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function eventTrigger(Node $statement): CreateEventTrigger
    {
        $form = $this->lowering->productions->form($statement);
        $names = $this->lowering->names;
        [$filters, $function] = match ($form->signature) {
            'CreateEventTrigStmt: CREATE EVENT TRIGGER name ON ColLabel EXECUTE FUNCTION_or_PROCEDURE func_name ( )' => [[], 8],
            'CreateEventTrigStmt: CREATE EVENT TRIGGER name ON ColLabel WHEN event_trigger_when_list EXECUTE FUNCTION_or_PROCEDURE func_name ( )' => [$this->filters($form->node(7)), 10],
            default => throw ImplementationGap::production($form),
        };

        $this->noise($form->node($function - 1), 'FUNCTION_or_PROCEDURE: FUNCTION', 'FUNCTION_or_PROCEDURE: PROCEDURE');

        return new CreateEventTrigger($names->name($form->node(3)), $names->name($form->node(5)), $names->dotted($form->node($function)), $filters);
    }

    /**
     * Lowers `event_trigger_when_list`.
     *
     * @return list<EventFilter>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function filters(Node $list): array
    {
        $filters = [];
        foreach ($this->lowering->items($list, 'event_trigger_when_list: event_trigger_when_item', 'event_trigger_when_list: event_trigger_when_list AND event_trigger_when_item') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'event_trigger_when_item: ColId IN_P ( event_trigger_value_list )') {
                throw ImplementationGap::production($form);
            }
            $values = [];
            $value = $this->lowering->productions->form($form->node(3));
            while ($value->signature === 'event_trigger_value_list: event_trigger_value_list , SCONST') {
                $values[] = $this->lowering->literals->stringToken($value->token(2));
                $value = $this->lowering->productions->form($value->node(0));
            }
            if ($value->signature !== 'event_trigger_value_list: SCONST') {
                throw ImplementationGap::production($value);
            }
            $values[] = $this->lowering->literals->stringToken($value->token(0));
            $filters[] = new EventFilter($this->lowering->names->name($form->node(0)), array_reverse($values));
        }

        return $filters;
    }

    /**
     * Lowers `AlterEventTrigStmt` with its `enable_trigger`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alterEventTrigger(Node $statement): AlterEventTrigger
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'AlterEventTrigStmt: ALTER EVENT TRIGGER name enable_trigger') {
            throw ImplementationGap::production($form);
        }
        $state = $this->lowering->productions->form($form->node(4));

        return new AlterEventTrigger($this->lowering->names->name($form->node(3)), self::STATES[$state->signature] ?? throw ImplementationGap::production($state));
    }

    /**
     * Checks a production that only writes noise words: it must be one of the given ones.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function noise(Node $words, string ...$signatures): void
    {
        $form = $this->lowering->productions->form($words);
        if (!in_array($form->signature, $signatures, true)) {
            throw ImplementationGap::production($form);
        }
    }
}
