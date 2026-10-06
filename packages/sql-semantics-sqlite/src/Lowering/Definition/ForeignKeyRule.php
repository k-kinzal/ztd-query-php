<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\Deferrability;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ForeignKeyClause;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\InitialMode;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\MatchName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceAction;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceArgument;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceEvent;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ReferenceReaction;

/**
 * Lowers the clauses of a foreign key.
 *
 * Rule: SQLITE-FOREIGN-KEY-LOWER-001. Scope: refargs, refarg, refact,
 * defer_subclause, defer_subclause_opt, init_deferred_pred_opt, and the
 * REFERENCES part shared by the column and the table constraint.
 * Constructors: ForeignKeyClause, MatchName, ReferenceAction, Deferrability.
 * The actions and MATCH names keep their written order; every keyword is a
 * model value. Terminates: the argument list is flattened iteratively.
 * Source: https://sqlite.org/syntax/foreign-key-clause.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ForeignKeyRule
{
    /**
     * The event each action production is written for.
     */
    private const EVENTS = [
        'refarg: ON INSERT refact' => ReferenceEvent::Insert,
        'refarg: ON DELETE refact' => ReferenceEvent::Delete,
        'refarg: ON UPDATE refact' => ReferenceEvent::Update,
    ];

    /**
     * The reaction each production spells.
     */
    private const REACTIONS = [
        'refact: SET NULL' => ReferenceReaction::SetNull,
        'refact: SET DEFAULT' => ReferenceReaction::SetDefault,
        'refact: CASCADE' => ReferenceReaction::Cascade,
        'refact: RESTRICT' => ReferenceReaction::Restrict,
        'refact: NO ACTION' => ReferenceReaction::NoAction,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the parent table, its optional column list and the clauses after it.
     */
    public function clause(Node $table, Node $columns, Node $arguments): ForeignKeyClause
    {
        $parent = $this->lowering->names->name($table);
        $listed = $this->lowering->ordering->optionalColumns($columns);

        return new ForeignKeyClause($parent, $listed, $this->arguments($arguments));
    }

    /**
     * Lowers the actions and MATCH names in written order.
     *
     * @return list<ReferenceArgument>
     * @throws ImplementationGap When a production has no rule
     */
    public function arguments(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature !== 'refargs:' && $form->signature !== 'refargs: refargs refarg') {
            throw ImplementationGap::production($form);
        }
        $arguments = [];
        foreach ((new Lists())->items($list) as $item) {
            $arguments[] = $this->argument($item);
        }

        return $arguments;
    }

    /**
     * Lowers one action or MATCH name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function argument(Node $argument): ReferenceArgument
    {
        $form = $this->lowering->productions->form($argument);
        if ($form->signature === 'refarg: MATCH nm') {
            return new MatchName($this->lowering->names->name($form->node(1)));
        }
        $reaction = $this->lowering->productions->form($form->node(2));

        return new ReferenceAction(
            self::EVENTS[$form->signature] ?? throw ImplementationGap::production($form),
            self::REACTIONS[$reaction->signature] ?? throw ImplementationGap::production($reaction),
        );
    }

    /**
     * Lowers a DEFERRABLE or NOT DEFERRABLE clause.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function deferrability(Node $clause): Deferrability
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'defer_subclause: NOT DEFERRABLE init_deferred_pred_opt' => new Deferrability(false, $this->initially($form->node(2))),
            'defer_subclause: DEFERRABLE init_deferred_pred_opt' => new Deferrability(true, $this->initially($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional DEFERRABLE clause of a table constraint.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalDeferrability(Node $clause): ?Deferrability
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'defer_subclause_opt:' => null,
            'defer_subclause_opt: defer_subclause' => $this->deferrability($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional INITIALLY clause.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function initially(Node $clause): ?InitialMode
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'init_deferred_pred_opt:' => null,
            'init_deferred_pred_opt: INITIALLY DEFERRED' => InitialMode::Deferred,
            'init_deferred_pred_opt: INITIALLY IMMEDIATE' => InitialMode::Immediate,
            default => throw ImplementationGap::production($form),
        };
    }
}
