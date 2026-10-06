<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Partition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionBound;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionMaximum;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesIn;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers partition and subpartition definitions with their values.
 *
 * Rule: MYSQL-PARTITION-DEFINITION-001. Scope: part_defs, opt_part_defs,
 * part_def_list, part_definition, part_name, opt_part_values,
 * part_func_max, part_values_in, part_value_list, part_value_item,
 * part_value_item_list, part_value_expr_item, part_value_item_list_paren,
 * opt_sub_partition, sub_part_list, sub_part_definition, sub_name. A
 * parenthesized row of values is PT_part_value_item_list_paren; `VALUES IN`
 * with one row is PT_part_values_in_item and with a parenthesized list of
 * rows PT_part_values_in_list; MAXVALUE without parentheses after LESS THAN
 * is the bound without a row. Constructs: PartitionDefinition,
 * SubpartitionDefinition, LessThan, ValuesIn, ValuesInRows, PartitionRow,
 * PartitionMaximum. Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class DefinitionRule
{
    /**
     * The list spines of partition and subpartition definitions.
     */
    private const SPINES = [
        'part_def_list: part_definition' => true, 'part_def_list: part_def_list , part_definition' => true,
        'sub_part_list: sub_part_definition' => true, 'sub_part_list: sub_part_list , sub_part_definition' => true,
    ];

    /**
     * The list spines of rows and of row items.
     */
    private const ROW_SPINES = [
        'part_value_list: part_value_item' => true, 'part_value_list: part_value_list , part_value_item' => true,
        'part_value_list: part_value_item_list_paren' => true, 'part_value_list: part_value_list , part_value_item_list_paren' => true,
        'part_value_item_list: part_value_expr_item' => true, 'part_value_item_list: part_value_item_list , part_value_expr_item' => true,
        'part_value_item_list: part_value_item' => true, 'part_value_item_list: part_value_item_list , part_value_item' => true,
    ];

    /**
     * The parenthesized row productions.
     */
    private const ROWS = ['part_value_item: ( part_value_item_list )' => true, 'part_value_item_list_paren: ( part_value_item_list )' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the definitions of a partitioning or of a partition change; an absent list is empty.
     *
     * @return list<PartitionDefinition>
     * @throws ImplementationGap When a production has no rule
     */
    public function definitions(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'part_defs:' || $form->signature === 'opt_part_defs:') {
            return [];
        }
        if ($form->signature === 'part_defs: ( part_def_list )' || $form->signature === 'opt_part_defs: ( part_def_list )') {
            return $this->definitions($form->node(1));
        }
        $this->spine($list);
        $definitions = [];
        foreach ((new Lists())->items($list) as $item) {
            $definitions[] = $this->definition($item);
        }

        return $definitions;
    }

    /**
     * Confirms that a node is a list spine this rule flattens.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function spine(Node $list): void
    {
        $form = $this->lowering->form($list);
        if (!isset(self::SPINES[$form->signature]) && !isset(self::ROW_SPINES[$form->signature])) {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers one partition definition: a node of `part_definition`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function definition(Node $definition): PartitionDefinition
    {
        $form = $this->lowering->form($definition);
        $name = match ($form->signature) {
            'part_definition: PARTITION_SYM part_name opt_part_values opt_part_options opt_sub_partition' => $this->lowering->form($form->node(1)),
            'part_definition: PARTITION_SYM ident opt_part_values opt_part_options opt_sub_partition' => null,
            default => throw ImplementationGap::production($form),
        };
        if ($name !== null && $name->signature !== 'part_name: ident') {
            throw ImplementationGap::production($name);
        }

        return new PartitionDefinition(
            $this->lowering->names->identifier($name === null ? $form->node(1) : $name->node(0)),
            $this->bound($form->node(2)),
            (new OptionRule($this->lowering))->options($form->node(3)),
            $this->subpartitions($form->node(4)),
        );
    }

    /**
     * Lowers the values of a partition: a node of `opt_part_values`; no values is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function bound(Node $values): ?PartitionBound
    {
        $form = $this->lowering->form($values);

        return match ($form->signature) {
            'opt_part_values:' => null,
            'opt_part_values: VALUES LESS_SYM THAN_SYM part_func_max' => $this->lessThan($form->node(3)),
            'opt_part_values: VALUES IN_SYM part_values_in' => $this->valuesIn($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the bound of a RANGE partition: a node of `part_func_max`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function lessThan(Node $bound): LessThan
    {
        $form = $this->lowering->form($bound);

        return match ($form->signature) {
            'part_func_max: MAX_VALUE_SYM' => new LessThan(null),
            'part_func_max: part_value_item', 'part_func_max: part_value_item_list_paren' => new LessThan($this->row($form->node(0))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the values of a LIST partition: a node of `part_values_in`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function valuesIn(Node $values): ValuesIn|ValuesInRows
    {
        $form = $this->lowering->form($values);
        if ($form->signature === 'part_values_in: part_value_item' || $form->signature === 'part_values_in: part_value_item_list_paren') {
            return new ValuesIn($this->row($form->node(0)));
        }
        if ($form->signature !== 'part_values_in: ( part_value_list )') {
            throw ImplementationGap::production($form);
        }
        $this->spine($form->node(1));
        $rows = [];
        foreach ((new Lists())->items($form->node(1)) as $item) {
            $rows[] = $this->row($item);
        }

        return new ValuesInRows($rows);
    }

    /**
     * Lowers a parenthesized row of values: a node of `part_value_item` (5.6, 5.7) or `part_value_item_list_paren`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function row(Node $row): PartitionRow
    {
        $form = $this->lowering->form($row);
        if (!isset(self::ROWS[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $this->spine($form->node(1));
        $items = [];
        foreach ((new Lists())->items($form->node(1)) as $item) {
            $items[] = $this->item($item);
        }

        return new PartitionRow($items);
    }

    /**
     * Lowers one value of a row: a node of `part_value_expr_item` (5.6, 5.7) or `part_value_item`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function item(Node $item): Scalar|PartitionMaximum
    {
        $form = $this->lowering->form($item);

        return match ($form->signature) {
            'part_value_expr_item: MAX_VALUE_SYM', 'part_value_item: MAX_VALUE_SYM' => new PartitionMaximum(),
            'part_value_expr_item: bit_expr', 'part_value_item: bit_expr' => $this->lowering->expressions->bitExpression($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the subpartitions of a partition: a node of `opt_sub_partition`; none is empty.
     *
     * @return list<SubpartitionDefinition>
     * @throws ImplementationGap When a production has no rule
     */
    public function subpartitions(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_sub_partition:') {
            return [];
        }
        if ($form->signature !== 'opt_sub_partition: ( sub_part_list )') {
            throw ImplementationGap::production($form);
        }
        $this->spine($form->node(1));
        $subpartitions = [];
        foreach ((new Lists())->items($form->node(1)) as $item) {
            $subpartitions[] = $this->subpartition($item);
        }

        return $subpartitions;
    }

    /**
     * Lowers one subpartition: a node of `sub_part_definition`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function subpartition(Node $definition): SubpartitionDefinition
    {
        $form = $this->lowering->form($definition);
        $name = match ($form->signature) {
            'sub_part_definition: SUBPARTITION_SYM sub_name opt_part_options' => $this->lowering->form($form->node(1)),
            'sub_part_definition: SUBPARTITION_SYM ident_or_text opt_part_options' => null,
            default => throw ImplementationGap::production($form),
        };
        if ($name !== null && $name->signature !== 'sub_name: ident_or_text') {
            throw ImplementationGap::production($name);
        }

        return new SubpartitionDefinition(
            $this->lowering->names->identifier($name === null ? $form->node(1) : $name->node(0)),
            (new OptionRule($this->lowering))->options($form->node(2)),
        );
    }
}
