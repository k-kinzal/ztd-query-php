<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Program\StatementRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Completion;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\Schedule;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers CREATE EVENT and ALTER EVENT.
 *
 * Rule: MYSQL-EVENT-LOWERING-001. Scope: event_tail, ev_schedule_time,
 * ev_starts, ev_ends, opt_ev_on_completion, ev_on_completion,
 * opt_ev_status, opt_ev_comment, ev_alter_on_schedule_completion,
 * opt_ev_rename_to, opt_ev_sql_stmt, alter_event_stmt and the `alter`
 * alternative of events (5.6, 5.7). The statement of the event is lowered
 * by MYSQL-PROGRAM-STATEMENT-LOWERING-001. Constructs: CreateEvent,
 * AlterEvent, OnceSchedule, RecurringSchedule. Terminates: every child is
 * a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-event.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-event.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class EventRule
{
    /**
     * The event definition productions, by the position of their first symbol after an optional marker.
     */
    private const TAILS = [
        'event_tail: remember_name EVENT_SYM opt_if_not_exists sp_name ON SCHEDULE_SYM ev_schedule_time opt_ev_on_completion opt_ev_status opt_ev_comment DO_SYM ev_sql_stmt' => 1,
        'event_tail: EVENT_SYM opt_if_not_exists sp_name ON SCHEDULE_SYM ev_schedule_time opt_ev_on_completion opt_ev_status opt_ev_comment DO_SYM ev_sql_stmt' => 0,
        'event_tail: EVENT_SYM opt_if_not_exists sp_name ON_SYM SCHEDULE_SYM ev_schedule_time opt_ev_on_completion opt_ev_status opt_ev_comment DO_SYM ev_sql_stmt' => 0,
    ];

    /**
     * The ALTER EVENT productions.
     */
    private const ALTERS = [
        'alter: ALTER definer_opt EVENT_SYM sp_name ev_alter_on_schedule_completion opt_ev_rename_to opt_ev_status opt_ev_comment opt_ev_sql_stmt' => true,
        'alter_event_stmt: ALTER definer_opt EVENT_SYM sp_name ev_alter_on_schedule_completion opt_ev_rename_to opt_ev_status opt_ev_comment opt_ev_sql_stmt' => true,
    ];

    /**
     * The status clauses.
     */
    private const STATUSES = [
        'opt_ev_status:' => null, 'opt_ev_status: ENABLE_SYM' => EventStatus::Enable, 'opt_ev_status: DISABLE_SYM' => EventStatus::Disable,
        'opt_ev_status: DISABLE_SYM ON SLAVE' => EventStatus::DisableOnSlave, 'opt_ev_status: DISABLE_SYM ON_SYM SLAVE' => EventStatus::DisableOnSlave,
        'opt_ev_status: DISABLE_SYM ON_SYM REPLICA_SYM' => EventStatus::DisableOnReplica,
    ];

    /**
     * The ON COMPLETION clauses.
     */
    private const COMPLETIONS = [
        'ev_on_completion: ON COMPLETION_SYM PRESERVE_SYM' => Completion::Preserve, 'ev_on_completion: ON COMPLETION_SYM NOT_SYM PRESERVE_SYM' => Completion::NotPreserve,
        'ev_on_completion: ON_SYM COMPLETION_SYM PRESERVE_SYM' => Completion::Preserve, 'ev_on_completion: ON_SYM COMPLETION_SYM NOT_SYM PRESERVE_SYM' => Completion::NotPreserve,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers CREATE EVENT: a node of `event_tail`, and the definer.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Node $tail, ?Account $definer): CreateEvent
    {
        $form = $this->lowering->form($tail);
        $first = self::TAILS[$form->signature] ?? throw ImplementationGap::production($form);
        if ($first === 1) {
            $this->lowering->options->skip($form->node(0));
        }
        $completion = $this->lowering->form($form->node($first + 6));

        $name = $this->lowering->names->qualified($form->node($first + 2));
        $schedule = $this->schedule($form->node($first + 5), $name, $definer);
        $body = (new StatementRule($this->lowering->inProgram($name)))->event($form->node($first + 10));
        (new DefinitionBody())->check($body, \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Event, $name, $definer, (new \SqlSemantics\Construction\SourceRanges())->span($tail)[1] ?? 0, nameEnd: (new \SqlSemantics\Construction\SourceRanges())->span($form->node($first + 2))[1] ?? 0, sources: $this->lowering->origins->publish());

        return new CreateEvent(
            $name,
            $schedule,
            $body,
            match ($completion->signature) {
                'opt_ev_on_completion:' => null,
                'opt_ev_on_completion: ev_on_completion' => $this->completion($completion->node(0)),
                default => throw ImplementationGap::production($completion),
            },
            $this->status($form->node($first + 7)),
            $this->comment($form->node($first + 8)),
            $definer,
            $this->lowering->options->present($form->node($first + 1)),
        );
    }

    /**
     * Lowers ALTER EVENT: the form of the 5.x `alter` alternative or of `alter_event_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function alter(Form $form): AlterEvent
    {
        if (!isset(self::ALTERS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $name = $this->lowering->names->qualified($form->node(3));
        $definer = $this->lowering->users->definer($form->node(1));
        $change = $this->lowering->form($form->node(4));
        [$schedule, $completion] = match ($change->signature) {
            'ev_alter_on_schedule_completion:' => [null, null],
            'ev_alter_on_schedule_completion: ON SCHEDULE_SYM ev_schedule_time', 'ev_alter_on_schedule_completion: ON_SYM SCHEDULE_SYM ev_schedule_time' => [$this->schedule($change->node(2), $name, $definer), null],
            'ev_alter_on_schedule_completion: ev_on_completion' => [null, $this->completion($change->node(0))],
            'ev_alter_on_schedule_completion: ON SCHEDULE_SYM ev_schedule_time ev_on_completion',
            'ev_alter_on_schedule_completion: ON_SYM SCHEDULE_SYM ev_schedule_time ev_on_completion' => [$this->schedule($change->node(2), $name, $definer), $this->completion($change->node(3))],
            default => throw ImplementationGap::production($change),
        };
        $rename = $this->lowering->form($form->node(5));
        $body = $this->lowering->form($form->node(8));

        $instruction = match ($body->signature) {
            'opt_ev_sql_stmt:' => null,
            'opt_ev_sql_stmt: DO_SYM ev_sql_stmt' => (new StatementRule($this->lowering->inProgram($name)))->event($body->node(1)),
            default => throw ImplementationGap::production($body),
        };
        if ($instruction !== null) {
            (new DefinitionBody())->check($instruction, \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Event, $name, $definer, (new \SqlSemantics\Construction\SourceRanges())->span($form->node)[1] ?? 0, nameEnd: (new \SqlSemantics\Construction\SourceRanges())->span($form->node(3))[1] ?? 0, sources: $this->lowering->origins->publish());
        }
        $status = $this->status($form->node(6));
        $comment = $this->comment($form->node(7));
        $refusal = $this->rejected($form->node);
        if ($refusal !== null) {
            throw $refusal;
        }

        return new AlterEvent(
            $name,
            $schedule,
            $completion,
            match ($rename->signature) {
                'opt_ev_rename_to:' => null,
                'opt_ev_rename_to: RENAME TO_SYM sp_name' => $this->lowering->names->qualified($rename->node(2)),
                default => throw ImplementationGap::production($rename),
            },
            $status,
            $comment,
            $instruction,
            $definer,
        );
    }

    /**
     * Refuses a completed event header whose optional change productions are all empty.
     */
    public function rejected(Node $node): ?\SqlSemantics\Construction\RejectedInput
    {
        if (!in_array($node->name, ['alter', 'alter_event_stmt'], true)) {
            return null;
        }
        $form = $this->lowering->form($node);
        if (!isset(self::ALTERS[$form->signature])) {
            return null;
        }
        for ($index = 4; $index <= 8; ++$index) {
            if ($form->node($index)->tokens() !== []) {
                return null;
            }
        }
        $end = (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0;
        $problem = new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\EmptyEventAlteration($this->lowering->names->qualified($form->node(3)), $this->lowering->users->definer($form->node(1)), $end);

        return new \SqlSemantics\Construction\RejectedInput($problem, $end);
    }

    /**
     * Lowers a schedule: a node of `ev_schedule_time`.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When a schedule operand has no scalar interpretation
     * @throws ImplementationGap When a production has no rule
     */
    public function schedule(Node $schedule, ?\SqlSemantics\Statement\Identifier\QualifiedName $name = null, ?Account $definer = null): Schedule
    {
        try {
            $form = $this->lowering->form($schedule);

            return match ($form->signature) {
                'ev_schedule_time: AT_SYM expr' => new OnceSchedule($this->expression($form->node(1), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::At)),
                'ev_schedule_time: EVERY_SYM expr interval ev_starts ev_ends' => new RecurringSchedule(
                    $this->expression($form->node(1), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::Interval),
                    $this->lowering->expressions->intervalUnit($form->node(2)),
                    $this->bound($form->node(3)),
                    $this->bound($form->node(4)),
                ),
                default => throw ImplementationGap::production($form),
            };
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            throw $name === null ? $failure : (new Event\ScheduleInput())->wrap($failure, $schedule, $name, $definer, $this->lowering->profile->grammar, $this->lowering);
        }
    }

    /**
     * Lowers the STARTS or ENDS clause: a node of `ev_starts` or `ev_ends`; an absent clause is null.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When the timestamp operand has no scalar interpretation
     * @throws ImplementationGap When a production has no rule
     */
    public function bound(Node $clause): ?Scalar
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'ev_starts:', 'ev_ends:' => null,
            'ev_starts: STARTS_SYM expr' => $this->expression($form->node(1), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::Starts),
            'ev_ends: ENDS_SYM expr' => $this->expression($form->node(1), \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause::Ends),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Preserves a rejected width's schedule role without retaining invalid expressions.
     *
     * @throws \SqlSemantics\Construction\RejectedInput When an operand has no scalar interpretation
     */
    public function expression(Node $node, \SqlSemantics\Platform\MySql\Statement\Routine\Event\Clause $clause): Scalar
    {
        $row = false;
        try {
            $operand = $this->lowering->expressions->operand($node);
            $position = (new \SqlSemantics\Platform\MySql\Rules\Query\Recursion\Constraints())->position($operand);
            if ($position !== null) {
                throw new \SqlSemantics\Construction\RejectedInput($position, (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0, false);
            }
            $row = $operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
            return (new \SqlSemantics\Platform\MySql\Lowering\Expression\OperandRule())->scalar($operand, $node);
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            $problem = $failure->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedScalar ? (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$failure->problem->operand]) : $failure->problem;
            if (!$problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns) {
                throw $failure;
            }
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Routine\Event\Problem\OperandWidth($problem, $clause, $row), $failure->end, $failure->aborts);
        }
    }

    /**
     * Lowers ON COMPLETION [NOT] PRESERVE: a node of `ev_on_completion`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function completion(Node $clause): Completion
    {
        $form = $this->lowering->form($clause);

        return self::COMPLETIONS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers the status clause: a node of `opt_ev_status`; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function status(Node $clause): ?EventStatus
    {
        $form = $this->lowering->form($clause);
        if (!array_key_exists($form->signature, self::STATUSES)) {
            throw ImplementationGap::production($form);
        }

        return self::STATUSES[$form->signature];
    }

    /**
     * Lowers the COMMENT clause: a node of `opt_ev_comment`; an absent clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function comment(Node $clause): ?Text
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_ev_comment:' => null,
            'opt_ev_comment: COMMENT_SYM TEXT_STRING_sys' => $this->lowering->literals->text($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
