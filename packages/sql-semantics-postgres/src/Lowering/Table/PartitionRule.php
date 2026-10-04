<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DefaultBound;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\HashBound;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\HashModulus;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\ListBound;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionBound;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionElement;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeBound;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\RangeLimit;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers partition keys and partition bounds.
 *
 * Rule: PG-PARTITION-LOWER-001. Scope: `OptPartitionSpec`, `PartitionSpec`,
 * `part_params`, `part_elem`, `PartitionBoundSpec`, `hash_partbound`,
 * `hash_partbound_elem`. A one-part column reference named `minvalue` or
 * `maxvalue` in a range bound is the infinite bound the server reads it as
 * (`transformPartitionRangeBounds`). Termination: lists are flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PartitionRule
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `OptPartitionSpec`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function specification(Node $clause): ?PartitionSpec
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature === 'OptPartitionSpec:') {
            return null;
        }
        if ($form->signature !== 'OptPartitionSpec: PartitionSpec') {
            throw ImplementationGap::production($form);
        }
        $spec = $this->lowering->productions->form($form->node(0));
        if ($spec->signature !== 'PartitionSpec: PARTITION BY ColId ( part_params )') {
            throw ImplementationGap::production($spec);
        }
        $elements = [];
        foreach ($this->lowering->items($spec->node(4), 'part_params: part_elem', 'part_params: part_params , part_elem') as $item) {
            $elements[] = $this->element($item);
        }

        return new PartitionSpec($this->lowering->names->name($spec->node(2)), $elements);
    }

    /**
     * Lowers `part_elem`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function element(Node $element): PartitionElement
    {
        $form = $this->lowering->productions->form($element);
        [$key, $at] = match ($form->signature) {
            'part_elem: ColId opt_collate opt_qualified_name' => [new ColumnKey($this->lowering->names->name($form->node(0))), 1],
            'part_elem: func_expr_windowless opt_collate opt_qualified_name' => [new ExpressionKey($this->lowering->invocations->call($form->node(0)), false), 1],
            'part_elem: ( a_expr ) opt_collate opt_qualified_name' => [new ExpressionKey($this->lowering->expressions->expression($form->node(1))), 3],
            default => throw ImplementationGap::production($form),
        };

        return new PartitionElement($key, $this->lowering->names->optionalDotted($form->node($at)), $this->lowering->names->optionalDotted($form->node($at + 1)));
    }

    /**
     * Lowers `PartitionBoundSpec`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bound(Node $bound): PartitionBound
    {
        $form = $this->lowering->productions->form($bound);

        return match ($form->signature) {
            'PartitionBoundSpec: FOR VALUES WITH ( hash_partbound )' => new HashBound($this->hash($form->node(4))),
            'PartitionBoundSpec: FOR VALUES IN_P ( expr_list )' => new ListBound($this->lowering->expressions->expressions($form->node(4))),
            'PartitionBoundSpec: FOR VALUES FROM ( expr_list ) TO ( expr_list )' => new RangeBound($this->datums($form->node(4)), $this->datums($form->node(8))),
            'PartitionBoundSpec: DEFAULT' => new DefaultBound(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `hash_partbound`.
     *
     * @return list<HashModulus>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function hash(Node $list): array
    {
        $items = [];
        foreach ($this->lowering->items($list, 'hash_partbound: hash_partbound_elem', 'hash_partbound: hash_partbound , hash_partbound_elem') as $item) {
            $form = $this->lowering->productions->form($item);
            if ($form->signature !== 'hash_partbound_elem: NonReservedWord Iconst') {
                throw ImplementationGap::production($form);
            }
            $items[] = new HashModulus($this->lowering->names->name($form->node(0)), $this->lowering->literals->integer($form->node(1)));
        }

        return $items;
    }

    /**
     * Lowers the `expr_list` of a range bound: MINVALUE and MAXVALUE become infinite bounds.
     *
     * @return list<Scalar|RangeLimit>
     */
    public function datums(Node $list): array
    {
        $datums = [];
        foreach ($this->lowering->expressions->expressions($list) as $value) {
            $infinite = $value instanceof ColumnReference && count($value->parts) === 1 && in_array($value->parts[0]->value, ['minvalue', 'maxvalue'], true);
            $datums[] = $infinite ? new RangeLimit($value->parts[0]) : $value;
        }

        return $datums;
    }
}
