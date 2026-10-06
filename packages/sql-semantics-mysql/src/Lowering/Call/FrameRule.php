<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\Window\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameExclusion;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers a parenthesized window specification and its frame.
 *
 * Rule: MYSQL-CALL-FRAME-001. Scope: window_spec, window_spec_details,
 * opt_existing_window_name, opt_partition_clause,
 * opt_window_order_by_clause, opt_window_frame_clause, window_frame_units,
 * window_frame_extent, window_frame_start, window_frame_between,
 * window_frame_bound, opt_window_frame_exclusion. The partitioning and the
 * ordering are lowered by the query family. Constructs: WindowSpec, Frame,
 * FrameBound. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-usage.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FrameRule
{
    /**
     * The boundary productions: the kind, and the positions of an offset and of an interval unit.
     */
    private const BOUNDS = [
        'window_frame_start: UNBOUNDED_SYM PRECEDING_SYM' => [FrameBoundKind::UnboundedPreceding, null, null],
        'window_frame_start: NUM_literal PRECEDING_SYM' => [FrameBoundKind::Preceding, 0, null],
        'window_frame_start: param_marker PRECEDING_SYM' => [FrameBoundKind::Preceding, 0, null],
        'window_frame_start: INTERVAL_SYM expr interval PRECEDING_SYM' => [FrameBoundKind::Preceding, 1, 2],
        'window_frame_start: CURRENT_SYM ROW_SYM' => [FrameBoundKind::CurrentRow, null, null],
        'window_frame_bound: UNBOUNDED_SYM FOLLOWING_SYM' => [FrameBoundKind::UnboundedFollowing, null, null],
        'window_frame_bound: NUM_literal FOLLOWING_SYM' => [FrameBoundKind::Following, 0, null],
        'window_frame_bound: param_marker FOLLOWING_SYM' => [FrameBoundKind::Following, 0, null],
        'window_frame_bound: INTERVAL_SYM expr interval FOLLOWING_SYM' => [FrameBoundKind::Following, 1, 2],
    ];

    /**
     * The frame unit productions.
     */
    private const UNITS = ['window_frame_units: ROWS_SYM' => FrameUnit::Rows, 'window_frame_units: RANGE_SYM' => FrameUnit::Range, 'window_frame_units: GROUPS_SYM' => FrameUnit::Groups];

    /**
     * The exclusion productions.
     */
    private const EXCLUSIONS = [
        'opt_window_frame_exclusion:' => null, 'opt_window_frame_exclusion: EXCLUDE_SYM CURRENT_SYM ROW_SYM' => FrameExclusion::CurrentRow,
        'opt_window_frame_exclusion: EXCLUDE_SYM GROUP_SYM' => FrameExclusion::Group, 'opt_window_frame_exclusion: EXCLUDE_SYM TIES_SYM' => FrameExclusion::Ties,
        'opt_window_frame_exclusion: EXCLUDE_SYM NO_SYM OTHERS_SYM' => FrameExclusion::NoOthers,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a window specification: a node of window_spec.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function specification(Node $specification): WindowSpec
    {
        $form = $this->lowering->form($specification);
        $this->lowering->names->claimed($form, ['window_spec: ( window_spec_details )']);
        $details = $this->lowering->form($form->node(1));
        $this->lowering->names->claimed($details, ['window_spec_details: opt_existing_window_name opt_partition_clause opt_window_order_by_clause opt_window_frame_clause']);

        return new WindowSpec($this->base($details->node(0)), $this->ordering($details->node(1)), $this->ordering($details->node(2)), $this->frame($details->node(3)));
    }

    /**
     * Lowers the window a specification refines: a node of opt_existing_window_name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function base(Node $name): ?Name
    {
        $form = $this->lowering->form($name);

        return match ($form->signature) {
            'opt_existing_window_name:' => null,
            'opt_existing_window_name: window_name' => $this->lowering->calls->windowName($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the partitioning or the ordering: a node of opt_partition_clause or opt_window_order_by_clause.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap When the production has no rule
     */
    public function ordering(Node $clause): array
    {
        $form = $this->lowering->form($clause);

        return match ($form->signature) {
            'opt_partition_clause:', 'opt_window_order_by_clause:' => [],
            'opt_partition_clause: PARTITION_SYM BY group_list', 'opt_window_order_by_clause: ORDER_SYM BY order_list' => $this->lowering->queries->ordering($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional frame: a node of opt_window_frame_clause.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function frame(Node $clause): ?Frame
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_window_frame_clause:') {
            return null;
        }
        if ($form->signature !== 'opt_window_frame_clause: window_frame_units window_frame_extent opt_window_frame_exclusion') {
            throw ImplementationGap::production($form);
        }
        $units = $this->lowering->form($form->node(0));
        $unit = self::UNITS[$units->signature] ?? throw ImplementationGap::production($units);
        $exclusion = $this->lowering->form($form->node(2));
        if (!array_key_exists($exclusion->signature, self::EXCLUSIONS)) {
            throw ImplementationGap::production($exclusion);
        }
        $extent = $this->lowering->form($form->node(1));
        if ($extent->signature === 'window_frame_extent: window_frame_start') {
            return new Frame($unit, $this->bound($extent->node(0)), null, self::EXCLUSIONS[$exclusion->signature]);
        }
        $this->lowering->names->claimed($extent, ['window_frame_extent: window_frame_between']);
        $between = $this->lowering->form($extent->node(0));
        $this->lowering->names->claimed($between, ['window_frame_between: BETWEEN_SYM window_frame_bound AND_SYM window_frame_bound']);

        return new Frame($unit, $this->bound($between->node(1)), $this->bound($between->node(3)), self::EXCLUSIONS[$exclusion->signature]);
    }

    /**
     * Lowers a boundary: a node of window_frame_start or window_frame_bound.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function bound(Node $bound): FrameBound
    {
        $form = $this->lowering->form($bound);
        if ($form->signature === 'window_frame_bound: window_frame_start') {
            $form = $this->lowering->form($form->node(0));
        }
        [$kind, $offset, $unit] = self::BOUNDS[$form->signature] ?? throw ImplementationGap::production($form);
        if ($offset === null) {
            return new FrameBound($kind);
        }
        if ($unit !== null) {
            return new FrameBound($kind, $this->lowering->expressions->expression($form->node($offset)), $this->lowering->expressions->intervalUnit($form->node($unit)));
        }
        $operand = $this->lowering->form($form->node($offset))->node->name === 'param_marker'
            ? $this->lowering->literals->parameter($form->node($offset))
            : $this->lowering->literals->number($form->node($offset));

        return new FrameBound($kind, $operand);
    }
}
