<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameExclusion;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowFrame;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers OVER clauses and window specifications.
 *
 * Rule: PG-WINDOW-LOWERING-001. Scope: `over_clause`,
 * `window_specification`, `opt_existing_window_name`, `opt_partition_clause`,
 * `opt_frame_clause`, `frame_extent`, `frame_bound`,
 * `opt_window_exclusion_clause`. Constructors: `WindowSpecification`,
 * `WindowFrame`, `FrameBound`. `EXCLUDE NO OTHERS` states the default and is
 * noise (see `InvocationNoise`). The frames the grammar's action rejects
 * (a start at UNBOUNDED FOLLOWING, an end at UNBOUNDED PRECEDING, a frame
 * that ends before it starts) raise an `AnalysisException` with the server's
 * message. Termination: the partition list is flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql\Lowering
 */
final class WindowRule
{
    /**
     * The frame bound kinds by production signature.
     */
    private const BOUNDS = [
        'frame_bound: UNBOUNDED PRECEDING' => FrameBoundKind::UnboundedPreceding,
        'frame_bound: UNBOUNDED FOLLOWING' => FrameBoundKind::UnboundedFollowing,
        'frame_bound: CURRENT_P ROW' => FrameBoundKind::CurrentRow,
        'frame_bound: a_expr PRECEDING' => FrameBoundKind::OffsetPreceding,
        'frame_bound: a_expr FOLLOWING' => FrameBoundKind::OffsetFollowing,
    ];

    /**
     * The frame exclusions by production signature; EXCLUDE NO OTHERS is no exclusion.
     */
    private const EXCLUSIONS = [
        'opt_window_exclusion_clause:' => null,
        'opt_window_exclusion_clause: EXCLUDE NO OTHERS' => null,
        'opt_window_exclusion_clause: EXCLUDE CURRENT_P ROW' => FrameExclusion::CurrentRow,
        'opt_window_exclusion_clause: EXCLUDE GROUP_P' => FrameExclusion::Group,
        'opt_window_exclusion_clause: EXCLUDE TIES' => FrameExclusion::Ties,
    ];

    /**
     * The frame modes by production signature.
     */
    private const MODES = [
        'opt_frame_clause: RANGE frame_extent opt_window_exclusion_clause' => FrameMode::Range,
        'opt_frame_clause: ROWS frame_extent opt_window_exclusion_clause' => FrameMode::Rows,
        'opt_frame_clause: GROUPS frame_extent opt_window_exclusion_clause' => FrameMode::Groups,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `over_clause`: a specification, a window name, or nothing.
     *
     * @throws AnalysisException When the frame is one the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function over(Node $clause): WindowSpecification|Name|null
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'over_clause:' => null,
            'over_clause: OVER window_specification' => $this->specification($form->node(1)),
            'over_clause: OVER ColId' => $this->lowering->names->name($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `window_specification`.
     *
     * @throws AnalysisException When the frame is one the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function specification(Node $specification): WindowSpecification
    {
        $form = $this->lowering->productions->form($specification);
        if ($form->signature !== 'window_specification: ( opt_existing_window_name opt_partition_clause opt_sort_clause opt_frame_clause )') {
            throw ImplementationGap::production($form);
        }
        $existing = $this->lowering->productions->form($form->node(1));
        $name = match ($existing->signature) {
            'opt_existing_window_name:' => null,
            'opt_existing_window_name: ColId' => $this->lowering->names->name($existing->node(0)),
            default => throw ImplementationGap::production($existing),
        };
        $partition = $this->lowering->productions->form($form->node(2));
        $expressions = match ($partition->signature) {
            'opt_partition_clause:' => [],
            'opt_partition_clause: PARTITION BY expr_list' => $this->lowering->expressions->expressions($partition->node(2)),
            default => throw ImplementationGap::production($partition),
        };

        return new WindowSpecification($name, $expressions, $this->lowering->queries->sortClause($form->node(3)), $this->frame($form->node(4)));
    }

    /**
     * Lowers `opt_frame_clause`; no clause is null.
     *
     * @throws AnalysisException When the frame is one the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function frame(Node $clause): ?WindowFrame
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'opt_frame_clause:') {
            return null;
        }
        $mode = self::MODES[$form->signature] ?? throw ImplementationGap::production($form);
        $exclusion = $this->lowering->productions->form($form->node(2));
        if (!array_key_exists($exclusion->signature, self::EXCLUSIONS)) {
            throw ImplementationGap::production($exclusion);
        }
        $extent = $this->lowering->productions->form($form->node(1));
        [$start, $end] = match ($extent->signature) {
            'frame_extent: frame_bound' => [$this->bound($extent->node(0)), null],
            'frame_extent: BETWEEN frame_bound AND frame_bound' => [$this->bound($extent->node(1)), $this->bound($extent->node(3))],
            default => throw ImplementationGap::production($extent),
        };
        $this->check($start->kind, $end?->kind);

        return new WindowFrame($mode, $start, $end, self::EXCLUSIONS[$exclusion->signature]);
    }

    /**
     * Rejects the frames the grammar's action rejects, with the server's messages.
     *
     * @throws AnalysisException When the frame is rejected
     */
    public function check(FrameBoundKind $start, ?FrameBoundKind $end): void
    {
        $last = $end ?? FrameBoundKind::CurrentRow;
        $problem = match (true) {
            $start === FrameBoundKind::UnboundedFollowing => 'frame start cannot be UNBOUNDED FOLLOWING',
            $end === null && $start === FrameBoundKind::OffsetFollowing => 'frame starting from following row cannot end with current row',
            $last === FrameBoundKind::UnboundedPreceding => 'frame end cannot be UNBOUNDED PRECEDING',
            $start === FrameBoundKind::CurrentRow && $last === FrameBoundKind::OffsetPreceding => 'frame starting from current row cannot have preceding rows',
            $start === FrameBoundKind::OffsetFollowing && ($last === FrameBoundKind::OffsetPreceding || $last === FrameBoundKind::CurrentRow) => 'frame starting from following row cannot have preceding rows',
            default => null,
        };
        if ($problem !== null) {
            throw new AnalysisException($problem);
        }
    }

    /**
     * Lowers `frame_bound`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bound(Node $bound): FrameBound
    {
        $form = $this->lowering->productions->form($bound);
        $kind = self::BOUNDS[$form->signature] ?? throw ImplementationGap::production($form);

        return new FrameBound($kind, $kind->offset() ? $this->lowering->expressions->expression($form->node(0)) : null);
    }
}
