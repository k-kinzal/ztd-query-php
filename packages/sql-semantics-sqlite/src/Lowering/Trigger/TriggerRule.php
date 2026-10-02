<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Trigger;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Values;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTable;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTiming;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers trigger definitions.
 *
 * Rule: SQLITE-TRIGGER-LOWER-001. Scope: trigger_decl, trigger_time,
 * trigger_event, foreach_clause, when_clause, trigger_cmd_list, trigger_cmd,
 * trnm, tridxby. The statements of the program keep their written order and
 * are lowered into the same statement classes as outside a trigger.
 * Terminates: the program is flattened iteratively.
 * Source: https://sqlite.org/lang_createtrigger.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriggerRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a trigger definition from its declaration and its program.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Node $declaration, Node $program): CreateTrigger
    {
        $lowering = $this->lowering;
        $form = $lowering->productions->form($declaration);
        if ($form->signature !== 'trigger_decl: temp TRIGGER ifnotexists nm dbnm trigger_time trigger_event ON fullname foreach_clause when_clause') {
            throw ImplementationGap::production($form);
        }
        $temporary = $lowering->flags->temporary($form->node(0));
        $ifNotExists = $lowering->flags->ifNotExists($form->node(2));
        $name = $lowering->names->scoped($form->node(3), $form->node(4));
        $timing = $this->timing($form->node(5));
        [$event, $columns] = $this->event($form->node(6));
        $table = new TriggerTable($lowering->names->qualified($form->node(8)));
        $each = $lowering->productions->form($form->node(9));
        $forEachRow = match ($each->signature) {
            'foreach_clause:' => false,
            'foreach_clause: FOR EACH ROW' => true,
            default => throw ImplementationGap::production($each),
        };
        $when = $this->when($form->node(10));

        return new CreateTrigger($name, $event, $table, $this->program($program), $timing, $columns, $when, $forEachRow, $temporary, $ifNotExists);
    }

    /**
     * Lowers a `trigger_time`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function timing(Node $time): ?TriggerTiming
    {
        $form = $this->lowering->productions->form($time);

        return match ($form->signature) {
            'trigger_time:' => null,
            'trigger_time: BEFORE|AFTER' => TriggerTiming::from(strtoupper($form->token(0)->text)),
            'trigger_time: INSTEAD OF' => TriggerTiming::InsteadOf,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `trigger_event` into the event and the columns of UPDATE OF.
     *
     * @return array{TriggerEvent, list<Name>}
     * @throws ImplementationGap When the production has no rule
     */
    public function event(Node $event): array
    {
        $form = $this->lowering->productions->form($event);

        return match ($form->signature) {
            'trigger_event: DELETE|INSERT' => [TriggerEvent::from(strtoupper($form->token(0)->text)), []],
            'trigger_event: UPDATE' => [TriggerEvent::Update, []],
            'trigger_event: UPDATE OF idlist' => [TriggerEvent::Update, $this->lowering->names->list($form->node(2))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `when_clause`: the condition, or null when the clause is absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function when(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'when_clause:' => null,
            'when_clause: WHEN expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `trigger_cmd_list` into the statements of the program in written order.
     *
     * @return list<Select|Values|Compound|WithQuery|InsertRows|InsertSelect|Update|Delete>
     * @throws ImplementationGap When a production has no rule
     */
    public function program(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'trigger_cmd_list: trigger_cmd_list trigger_cmd SEMI' && $form->signature !== 'trigger_cmd_list: trigger_cmd SEMI') {
            throw ImplementationGap::production($form);
        }
        $steps = [];
        foreach ((new Lists())->items($list) as $command) {
            $steps[] = $this->step($command);
        }

        return $steps;
    }

    /**
     * Lowers one `trigger_cmd`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function step(Node $command): Select|Values|Compound|WithQuery|InsertRows|InsertSelect|Update|Delete
    {
        $lowering = $this->lowering;
        $form = $lowering->productions->form($command);
        if ($form->signature === 'trigger_cmd: UPDATE orconf trnm tridxby SET setlist from where_opt scanpt') {
            $resolution = $lowering->conflicts->orConflict($form->node(1));
            $target = $this->target($form->node(2), $form->node(3));
            $assignments = $lowering->mutations->assignments($form->node(5));
            $from = $lowering->inputs->from($form->node(6));

            return new Update($target, $assignments, $from, $lowering->expressions->where($form->node(7)), [], $resolution);
        }

        return match ($form->signature) {
            'trigger_cmd: scanpt insert_cmd INTO trnm idlist_opt select upsert scanpt' => $lowering->mutations->insert($lowering->mutations->into($form->node(1), $this->target($form->node(3), null), $form->node(4), null), $form->node(5), $form->node(6)),
            'trigger_cmd: DELETE FROM trnm tridxby where_opt scanpt' => new Delete($this->target($form->node(2), $form->node(3)), $lowering->expressions->where($form->node(4))),
            'trigger_cmd: scanpt select scanpt' => $lowering->selects->select($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the written table of a program statement from its `trnm` and its optional `tridxby`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function target(Node $name, ?Node $indexed): MutationTarget
    {
        $form = $this->lowering->productions->form($name);
        $names = $this->lowering->names;
        $table = match ($form->signature) {
            'trnm: nm' => new QualifiedName($names->name($form->node(0))),
            'trnm: nm DOT nm' => $names->pair($form->node(0), $form->node(2)),
            default => throw ImplementationGap::production($form),
        };
        if ($indexed === null) {
            return new MutationTarget($table);
        }
        $choice = $this->lowering->productions->form($indexed);

        return match ($choice->signature) {
            'tridxby:' => new MutationTarget($table),
            'tridxby: INDEXED BY nm' => new MutationTarget($table, null, new IndexChoice($names->name($choice->node(2)))),
            'tridxby: NOT INDEXED' => new MutationTarget($table, null, new IndexChoice()),
            default => throw ImplementationGap::production($choice),
        };
    }
}
