<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\Frame;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameExclusion;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;

/**
 * Lowers window specifications, frames and the WINDOW clause.
 *
 * Rule: SQLITE-WINDOW-LOWER-001. Scope: window, frame_opt, range_or_rows,
 * frame_bound_s, frame_bound_e, frame_bound, frame_exclude_opt,
 * frame_exclude, window_clause, windowdefn_list, windowdefn. A window keeps
 * its base window name, partition, ordering and frame as written.
 * Terminates: lists are flattened iteratively; every part is a strict
 * subtree. Source: https://sqlite.org/windowfunctions.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class WindowRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a `window` specification.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function window(Node $window): WindowSpec
    {
        $form = $this->lowering->productions->form($window);
        $names = $this->lowering->names;
        $expressions = $this->lowering->expressions;
        $ordering = $this->lowering->ordering;

        return match ($form->signature) {
            'window: PARTITION BY nexprlist orderby_opt frame_opt' => new WindowSpec(null, $expressions->items($form->node(2)), $ordering->orderBy($form->node(3)), $this->frame($form->node(4))),
            'window: nm PARTITION BY nexprlist orderby_opt frame_opt' => new WindowSpec($names->name($form->node(0)), $expressions->items($form->node(3)), $ordering->orderBy($form->node(4)), $this->frame($form->node(5))),
            'window: ORDER BY sortlist frame_opt' => new WindowSpec(null, [], $ordering->terms($form->node(2)), $this->frame($form->node(3))),
            'window: nm ORDER BY sortlist frame_opt' => new WindowSpec($names->name($form->node(0)), [], $ordering->terms($form->node(3)), $this->frame($form->node(4))),
            'window: frame_opt' => new WindowSpec(null, [], [], $this->frame($form->node(0))),
            'window: nm frame_opt' => new WindowSpec($names->name($form->node(0)), [], [], $this->frame($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `frame_opt`: the frame, or null when none is written.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function frame(Node $frame): ?Frame
    {
        $form = $this->lowering->productions->form($frame);
        if ($form->signature === 'frame_opt:') {
            return null;
        }
        $unit = $this->lowering->productions->form($form->node(0));
        if ($unit->signature !== 'range_or_rows: RANGE|ROWS|GROUPS') {
            throw ImplementationGap::production($unit);
        }
        $kind = FrameUnit::from(strtoupper($unit->token(0)->text));

        return match ($form->signature) {
            'frame_opt: range_or_rows frame_bound_s frame_exclude_opt' => new Frame($kind, $this->bound($form->node(1)), null, $this->exclusion($form->node(2))),
            'frame_opt: range_or_rows BETWEEN frame_bound_s AND frame_bound_e frame_exclude_opt' => new Frame($kind, $this->bound($form->node(2)), $this->bound($form->node(4)), $this->exclusion($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a frame boundary of any of the three boundary productions.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bound(Node $bound): FrameBound
    {
        $form = $this->lowering->productions->form($bound);

        return match ($form->signature) {
            'frame_bound_s: frame_bound', 'frame_bound_e: frame_bound' => $this->bound($form->node(0)),
            'frame_bound_s: UNBOUNDED PRECEDING' => new FrameBound(FrameBoundKind::UnboundedPreceding),
            'frame_bound_e: UNBOUNDED FOLLOWING' => new FrameBound(FrameBoundKind::UnboundedFollowing),
            'frame_bound: expr PRECEDING|FOLLOWING' => new FrameBound($form->token(1)->name === 'PRECEDING' ? FrameBoundKind::Preceding : FrameBoundKind::Following, $this->lowering->expressions->expression($form->node(0))),
            'frame_bound: CURRENT ROW' => new FrameBound(FrameBoundKind::CurrentRow),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `frame_exclude_opt`: the exclusion, or null when none is written.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function exclusion(Node $clause): ?FrameExclusion
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'frame_exclude_opt:') {
            return null;
        }
        if ($form->signature !== 'frame_exclude_opt: EXCLUDE frame_exclude') {
            throw ImplementationGap::production($form);
        }
        $exclude = $this->lowering->productions->form($form->node(1));

        return match ($exclude->signature) {
            'frame_exclude: NO OTHERS' => FrameExclusion::NoOthers,
            'frame_exclude: CURRENT ROW' => FrameExclusion::CurrentRow,
            'frame_exclude: GROUP|TIES' => FrameExclusion::from(strtoupper($exclude->token(0)->text)),
            default => throw ImplementationGap::production($exclude),
        };
    }

    /**
     * Lowers a `window_clause` into its definitions in written order.
     *
     * @return list<WindowDefinition>
     * @throws ImplementationGap When a production has no rule
     */
    public function definitions(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature !== 'window_clause: WINDOW windowdefn_list') {
            throw ImplementationGap::production($form);
        }
        $list = $this->lowering->productions->form($form->node(1));
        if ($list->signature !== 'windowdefn_list: windowdefn' && $list->signature !== 'windowdefn_list: windowdefn_list COMMA windowdefn') {
            throw ImplementationGap::production($list);
        }
        $definitions = [];
        foreach ((new Lists())->items($form->node(1)) as $item) {
            $definition = $this->lowering->productions->form($item);
            if ($definition->signature !== 'windowdefn: nm AS LP window RP') {
                throw ImplementationGap::production($definition);
            }
            $definitions[] = new WindowDefinition($this->lowering->names->name($definition->node(0)), $this->window($definition->node(3)));
        }

        return $definitions;
    }
}
