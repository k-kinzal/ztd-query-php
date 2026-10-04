<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Manipulation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\Close;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\CursorOption;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\DeclareCursor;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\Fetch;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\FetchMovement;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\Holdability;

/**
 * Lowers DECLARE, FETCH, MOVE and CLOSE.
 *
 * Rule: PG-CURSOR-LOWER-001. Scope: `DeclareCursorStmt`, `cursor_options`,
 * `opt_hold`, `FetchStmt`, `fetch_args`, `from_in`, `opt_from_in`,
 * `ClosePortalStmt`. Constructors: `DeclareCursor` with `CursorOption` and
 * `Holdability`, `Fetch` with `FetchMovement`, `Close`. FROM and IN before
 * the cursor name are noise words. Termination: the option list is walked
 * down its spine in a loop.
 * Source: https://www.postgresql.org/docs/17/sql-declare.html, https://www.postgresql.org/docs/17/sql-fetch.html,
 * https://www.postgresql.org/docs/17/sql-close.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CursorRule
{
    /**
     * The option each `cursor_options` production adds.
     */
    private const OPTIONS = [
        'cursor_options: cursor_options NO SCROLL' => CursorOption::NoScroll,
        'cursor_options: cursor_options SCROLL' => CursorOption::Scroll,
        'cursor_options: cursor_options BINARY' => CursorOption::Binary,
        'cursor_options: cursor_options ASENSITIVE' => CursorOption::Asensitive,
        'cursor_options: cursor_options INSENSITIVE' => CursorOption::Insensitive,
    ];

    /**
     * The movement of each `fetch_args` production, and the positions of its count, of its cursor name and of its FROM or IN.
     */
    private const MOVEMENTS = [
        'fetch_args: cursor_name' => [FetchMovement::Implicit, null, 0, null],
        'fetch_args: from_in cursor_name' => [FetchMovement::Implicit, null, 1, 0],
        'fetch_args: NEXT opt_from_in cursor_name' => [FetchMovement::Next, null, 2, 1],
        'fetch_args: PRIOR opt_from_in cursor_name' => [FetchMovement::Prior, null, 2, 1],
        'fetch_args: FIRST_P opt_from_in cursor_name' => [FetchMovement::First, null, 2, 1],
        'fetch_args: LAST_P opt_from_in cursor_name' => [FetchMovement::Last, null, 2, 1],
        'fetch_args: ABSOLUTE_P SignedIconst opt_from_in cursor_name' => [FetchMovement::Absolute, 1, 3, 2],
        'fetch_args: RELATIVE_P SignedIconst opt_from_in cursor_name' => [FetchMovement::Relative, 1, 3, 2],
        'fetch_args: SignedIconst opt_from_in cursor_name' => [FetchMovement::Count, 0, 2, 1],
        'fetch_args: ALL opt_from_in cursor_name' => [FetchMovement::All, null, 2, 1],
        'fetch_args: FORWARD opt_from_in cursor_name' => [FetchMovement::Forward, null, 2, 1],
        'fetch_args: FORWARD SignedIconst opt_from_in cursor_name' => [FetchMovement::ForwardCount, 1, 3, 2],
        'fetch_args: FORWARD ALL opt_from_in cursor_name' => [FetchMovement::ForwardAll, null, 3, 2],
        'fetch_args: BACKWARD opt_from_in cursor_name' => [FetchMovement::Backward, null, 2, 1],
        'fetch_args: BACKWARD SignedIconst opt_from_in cursor_name' => [FetchMovement::BackwardCount, 1, 3, 2],
        'fetch_args: BACKWARD ALL opt_from_in cursor_name' => [FetchMovement::BackwardAll, null, 3, 2],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `DeclareCursorStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function declare(Node $statement): DeclareCursor
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'DeclareCursorStmt: DECLARE cursor_name cursor_options CURSOR opt_hold FOR SelectStmt') {
            throw ImplementationGap::production($form);
        }
        $hold = $this->lowering->productions->form($form->node(4));
        $hold = match ($hold->signature) {
            'opt_hold: WITH HOLD' => Holdability::With,
            'opt_hold: WITHOUT HOLD' => Holdability::Without,
            'opt_hold:' => null,
            default => throw ImplementationGap::production($hold),
        };

        return new DeclareCursor((new ChangeRule($this->lowering))->cursor($form->node(1)), $this->options($form->node(2)), $hold, $this->lowering->queries->query($form->node(6)));
    }

    /**
     * Lowers `cursor_options`.
     *
     * @return list<CursorOption>
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $options): array
    {
        $written = [];
        for ($form = $this->lowering->productions->form($options); $form->signature !== 'cursor_options:'; $form = $this->lowering->productions->form($form->node(0))) {
            $written[] = self::OPTIONS[$form->signature] ?? throw ImplementationGap::production($form);
        }

        return array_reverse($written);
    }

    /**
     * Lowers `FetchStmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function fetch(Node $statement): Fetch
    {
        $form = $this->lowering->productions->form($statement);
        $move = match ($form->signature) {
            'FetchStmt: FETCH fetch_args' => false,
            'FetchStmt: MOVE fetch_args' => true,
            default => throw ImplementationGap::production($form),
        };
        $arguments = $this->lowering->productions->form($form->node(1));
        [$movement, $count, $cursor, $from] = self::MOVEMENTS[$arguments->signature] ?? throw ImplementationGap::production($arguments);
        if ($from !== null) {
            $this->from($arguments->node($from));
        }

        return new Fetch(
            $move,
            $movement,
            $count === null ? null : $this->lowering->literals->signed($arguments->node($count)),
            (new ChangeRule($this->lowering))->cursor($arguments->node($cursor)),
        );
    }

    /**
     * Accepts `from_in` or `opt_from_in`: noise words that request nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function from(Node $words): void
    {
        $form = $this->lowering->productions->form($words);
        match ($form->signature) {
            'opt_from_in: from_in' => $this->from($form->node(0)),
            'from_in: FROM', 'from_in: IN_P', 'opt_from_in:' => null,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ClosePortalStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function close(Node $statement): Close
    {
        $form = $this->lowering->productions->form($statement);

        return match ($form->signature) {
            'ClosePortalStmt: CLOSE cursor_name' => new Close((new ChangeRule($this->lowering))->cursor($form->node(1))),
            'ClosePortalStmt: CLOSE ALL' => new Close(null),
            default => throw ImplementationGap::production($form),
        };
    }
}
