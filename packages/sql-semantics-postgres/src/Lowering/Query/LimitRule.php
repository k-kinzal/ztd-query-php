<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockStrength;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockWait;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\CommaLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\FetchFirst;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\Offset;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the LIMIT, OFFSET, FETCH and locking clauses.
 *
 * Rule: PG-LIMIT-LOWER-001. Scope: `opt_select_limit`, `select_limit`,
 * `limit_clause`, `offset_clause`, `select_limit_value`,
 * `select_offset_value`, `select_fetch_first_value`, `first_or_next`,
 * `row_or_rows`, `opt_for_locking_clause`, `for_locking_clause`,
 * `for_locking_items`, `for_locking_item`, `for_locking_strength`,
 * `locked_rels_list`, `opt_nowait_or_skip`. Constructors: `RowLimit`,
 * `LimitCount`, `CommaLimit`, `FetchFirst`, `Offset`, `LockingClause`. FIRST
 * and NEXT, ROW and ROWS are noise words; a plus sign before a numeric
 * constant in FETCH and OFFSET … ROWS is dropped by the server, a minus sign
 * negates it as it does in an expression. Termination: lists are flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-FOR-UPDATE-SHARE. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class LimitRule
{
    /**
     * The lock of each `for_locking_strength` production.
     */
    private const STRENGTHS = [
        'for_locking_strength: FOR UPDATE' => LockStrength::Update,
        'for_locking_strength: FOR NO KEY UPDATE' => LockStrength::NoKeyUpdate,
        'for_locking_strength: FOR SHARE' => LockStrength::Share,
        'for_locking_strength: FOR KEY SHARE' => LockStrength::KeyShare,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `opt_select_limit` or `select_limit`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function limit(Node $limit): ?RowLimit
    {
        $form = $this->lowering->productions->form($limit);

        return match ($form->signature) {
            'opt_select_limit:' => null,
            'opt_select_limit: select_limit' => $this->limit($form->node(0)),
            'select_limit: limit_clause offset_clause' => new RowLimit($this->count($form->node(0)), $this->offset($form->node(1))),
            'select_limit: offset_clause limit_clause' => new RowLimit($this->count($form->node(1)), $this->offset($form->node(0)), true),
            'select_limit: limit_clause' => new RowLimit($this->count($form->node(0))),
            'select_limit: offset_clause' => new RowLimit(null, $this->offset($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `limit_clause`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function count(Node $clause): LimitCount|CommaLimit|FetchFirst
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'limit_clause: LIMIT select_limit_value' => new LimitCount($this->limitValue($form->node(1))),
            'limit_clause: LIMIT select_limit_value , select_offset_value' => new CommaLimit($this->limitValue($form->node(1)), $this->offsetValue($form->node(3))),
            'limit_clause: FETCH first_or_next select_fetch_first_value row_or_rows ONLY' => $this->fetch($form, $this->fetchValue($form->node(2)), false, 3),
            'limit_clause: FETCH first_or_next select_fetch_first_value row_or_rows WITH TIES' => $this->fetch($form, $this->fetchValue($form->node(2)), true, 3),
            'limit_clause: FETCH first_or_next row_or_rows ONLY' => $this->fetch($form, null, false, 2),
            'limit_clause: FETCH first_or_next row_or_rows WITH TIES' => $this->fetch($form, null, true, 2),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Builds a FETCH FIRST clause after claiming its `first_or_next` and `row_or_rows` noise words.
     */
    public function fetch(Form $form, ?Scalar $count, bool $withTies, int $rows): FetchFirst
    {
        $this->noise($form->node(1), 'first_or_next: FIRST_P', 'first_or_next: NEXT');
        $this->rows($form->node($rows));

        return new FetchFirst($count, $withTies);
    }

    /**
     * Lowers `offset_clause`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function offset(Node $clause): Offset
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'offset_clause: OFFSET select_offset_value' => new Offset($this->offsetValue($form->node(1))),
            'offset_clause: OFFSET select_fetch_first_value row_or_rows' => $this->rowsOffset($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `OFFSET select_fetch_first_value row_or_rows`.
     */
    public function rowsOffset(Form $form): Offset
    {
        $this->rows($form->node(2));

        return new Offset($this->fetchValue($form->node(1)));
    }

    /**
     * Claims a `row_or_rows` noise word.
     */
    public function rows(Node $rows): void
    {
        $this->noise($rows, 'row_or_rows: ROW', 'row_or_rows: ROWS');
    }

    /**
     * Checks that a noise nonterminal is one of its productions.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function noise(Node $node, string ...$signatures): void
    {
        $form = $this->lowering->productions->form($node);
        if (!in_array($form->signature, $signatures, true)) {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers `select_limit_value`; ALL is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function limitValue(Node $value): ?Scalar
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'select_limit_value: a_expr' => $this->lowering->expressions->expression($form->node(0)),
            'select_limit_value: ALL' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `select_offset_value`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function offsetValue(Node $value): Scalar
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'select_offset_value: a_expr' => $this->lowering->expressions->expression($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `select_fetch_first_value`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function fetchValue(Node $value): Scalar
    {
        $form = $this->lowering->productions->form($value);

        return match ($form->signature) {
            'select_fetch_first_value: c_expr' => $this->lowering->expressions->expression($form->node(0)),
            'select_fetch_first_value: + I_or_F_const' => new Constant($this->lowering->literals->number($form->node(1))),
            'select_fetch_first_value: - I_or_F_const' => $this->lowering->expressions->negation($form->token(0), new Constant($this->lowering->literals->number($form->node(1)))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_for_locking_clause` or `for_locking_clause`: the locking clauses and whether FOR READ ONLY is written.
     *
     * @return array{list<LockingClause>, bool}
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function locking(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_for_locking_clause:' => [[], false],
            'opt_for_locking_clause: for_locking_clause' => $this->locking($form->node(0)),
            'for_locking_clause: FOR READ ONLY' => [[], true],
            'for_locking_clause: for_locking_items' => [$this->items($form->node(0)), false],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `for_locking_items`.
     *
     * @return list<LockingClause>
     *
     * @throws ImplementationGap When an item has no rule
     */
    public function items(Node $items): array
    {
        $clauses = [];
        foreach ($this->lowering->items($items, 'for_locking_items: for_locking_item', 'for_locking_items: for_locking_items for_locking_item') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'for_locking_item: for_locking_strength locked_rels_list opt_nowait_or_skip') {
                throw ImplementationGap::production($form);
            }
            $strength = $this->lowering->productions->form($form->node(0));
            $relations = $this->lowering->productions->form($form->node(1));
            $clauses[] = new LockingClause(
                self::STRENGTHS[$strength->signature] ?? throw ImplementationGap::production($strength),
                match ($relations->signature) {
                    'locked_rels_list: OF qualified_name_list' => $this->lowering->names->qualifiedList($relations->node(1)),
                    'locked_rels_list:' => [],
                    default => throw ImplementationGap::production($relations),
                },
                $this->wait($form->node(2)),
            );
        }

        return $clauses;
    }

    /**
     * Lowers `opt_nowait_or_skip`; waiting is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function wait(Node $wait): ?LockWait
    {
        $form = $this->lowering->productions->form($wait);

        return match ($form->signature) {
            'opt_nowait_or_skip: NOWAIT' => LockWait::NoWait,
            'opt_nowait_or_skip: SKIP LOCKED' => LockWait::SkipLocked,
            'opt_nowait_or_skip:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
