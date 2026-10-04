<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Alter;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnVisibility;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\DefaultSetting;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\Reorder;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;

/**
 * Lowers the column actions of ALTER TABLE.
 *
 * Rule: MYSQL-ALTER-COLUMN-001. Scope: the alter_list_item productions that
 * add, change, modify or alter columns, add_column, opt_column, opt_place,
 * alter_order_clause, alter_order_list, alter_order_item. Column
 * definitions are lowered by the table definition family
 * (TableDefinitionRules::tableElement, columnSpecification, tableElements);
 * an 8.0 column name is an unqualified ColumnName, a 5.x `field_ident` may be
 * qualified. COLUMN is an optional word. Constructs: AddColumn, AddColumns,
 * ChangeColumn (CHANGE and MODIFY), DefaultSetting, ColumnVisibility,
 * ColumnPosition, Reorder. Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class ColumnItemRule
{
    /**
     * The productions that hold a column definition, by the action and the positions of the old name, the definition, its second part and the position.
     */
    private const DEFINED = [
        'alter_list_item: add_column column_def opt_place' => ['add', null, 1, null, 2],
        'alter_list_item: ADD opt_column ident field_def opt_references opt_place' => ['add', 2, 3, 4, 5],
        'alter_list_item: CHANGE opt_column field_ident field_spec opt_place' => ['change', 2, 3, null, 4],
        'alter_list_item: CHANGE opt_column ident ident field_def opt_place' => ['change', 2, 4, 3, 5],
        'alter_list_item: MODIFY_SYM opt_column field_ident type opt_attribute opt_place' => ['modify', 2, 3, 4, 5],
        'alter_list_item: MODIFY_SYM opt_column field_ident field_def opt_place' => ['modify', 2, 3, null, 4],
        'alter_list_item: MODIFY_SYM opt_column ident field_def opt_place' => ['modify', 2, 3, null, 4],
    ];

    /**
     * The productions that alter a named column, by the action and the positions of the name and the operand.
     */
    private const ALTERED = [
        'alter_list_item: ALTER opt_column field_ident SET DEFAULT signed_literal' => ['default', 2, 5],
        'alter_list_item: ALTER opt_column ident SET_SYM DEFAULT_SYM signed_literal_or_null' => ['default', 2, 5],
        'alter_list_item: ALTER opt_column ident SET_SYM DEFAULT_SYM ( expr )' => ['expression', 2, 6],
        'alter_list_item: ALTER opt_column field_ident DROP DEFAULT' => ['default', 2, null],
        'alter_list_item: ALTER opt_column ident DROP DEFAULT_SYM' => ['default', 2, null],
        'alter_list_item: ALTER opt_column ident SET_SYM visibility' => ['visibility', 2, 4],
    ];

    /**
     * The productions that hold a list, by the action and the position of the list.
     */
    private const LISTED = [
        'alter_list_item: add_column ( create_field_list )' => ['adds', 2], 'alter_list_item: ADD opt_column ( table_element_list )' => ['adds', 3],
        'alter_list_item: alter_order_clause' => ['order', 0], 'alter_list_item: ORDER_SYM BY alter_order_list' => ['order', 2],
    ];

    /**
     * The productions of ordering items, by the position of the direction.
     */
    private const ORDER_ITEMS = ['alter_order_item: simple_ident_nospvar order_dir' => 1, 'alter_order_item: simple_ident_nospvar opt_ordering_direction' => 1];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether a production of `alter_list_item` is a column action.
     */
    public function claims(string $signature): bool
    {
        return isset(self::DEFINED[$signature]) || isset(self::ALTERED[$signature]) || isset(self::LISTED[$signature]);
    }

    /**
     * Lowers a column action of `alter_list_item`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function item(Form $form): AlterCommand
    {
        $this->column($form);
        if (isset(self::LISTED[$form->signature])) {
            [$kind, $list] = self::LISTED[$form->signature];

            return $kind === 'adds' ? new AddColumns($this->lowering->tableDefinitions->tableElements($form->node($list))) : new Reorder($this->order($form->node($list)));
        }
        if (isset(self::ALTERED[$form->signature])) {
            return $this->altered($form);
        }
        [$kind, $name, $operand, $second, $place] = self::DEFINED[$form->signature] ?? throw ImplementationGap::production($form);
        $position = $this->position($form, $place);

        return match ($kind) {
            'add' => new AddColumn($this->definition($form, $name, $operand, $second), $position),
            'change' => new ChangeColumn($this->name($form->node(2)), $this->definition($form, $second, $operand, null), $position),
            'modify' => new ChangeColumn(null, $this->definition($form, $name, $operand, $second), $position),
        };
    }

    /**
     * Lowers an ALTER COLUMN action: a default or a visibility.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function altered(Form $form): AlterCommand
    {
        [$kind, $name, $operand] = self::ALTERED[$form->signature] ?? throw ImplementationGap::production($form);
        $column = $this->name($form->node($name));
        if ($operand === null) {
            return new DefaultSetting($column, null);
        }

        return match ($kind) {
            'default' => new DefaultSetting($column, $this->lowering->literals->literal($form->node($operand))),
            'expression' => new DefaultSetting($column, $this->lowering->expressions->expression($form->node($operand)), true),
            'visibility' => new ColumnVisibility($column, $this->lowering->tableDefinitions->visible($form->node($operand))),
        };
    }

    /**
     * Confirms the optional COLUMN keyword of an action, or of its `add_column` prefix.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function column(Form $form): void
    {
        $first = $form->node->children[0] ?? null;
        if ($first instanceof Node && $first->name === 'add_column') {
            $prefix = $this->lowering->form($first);
            if ($prefix->signature !== 'add_column: ADD opt_column') {
                throw ImplementationGap::production($prefix);
            }
            $this->optional($prefix->node(1));

            return;
        }
        $second = $form->node->children[1] ?? null;
        if ($second instanceof Node && $second->name === 'opt_column') {
            $this->optional($second);
        }
    }

    /**
     * Confirms a node of `opt_column`, whose keyword has no effect.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function optional(Node $column): void
    {
        $form = $this->lowering->form($column);
        if ($form->signature !== 'opt_column:' && $form->signature !== 'opt_column: COLUMN_SYM') {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers a column definition: from a `column_def` or `field_spec` node, or from a name and its specification.
     *
     * @param int|null $name The position of the name, or null when the operand holds it
     * @param int $operand The position of the definition or of the type
     * @param int|null $second The position of the second part of the specification, when there is one
     * @throws ImplementationGap When a production has no rule
     */
    public function definition(Form $form, ?int $name, int $operand, ?int $second): ColumnDefinition
    {
        $definitions = $this->lowering->tableDefinitions;
        if ($name === null) {
            $element = $definitions->tableElement($form->node($operand));
            Check::invariant($element instanceof ColumnDefinition, 'A column definition lowers to a column definition.');

            return $element;
        }

        return new ColumnDefinition($this->name($form->node($name)), $definitions->columnSpecification($form->node($operand), $second === null ? null : $form->node($second)));
    }

    /**
     * Lowers the name of an altered column: a node of `field_ident` (5.x) or `ident`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function name(Node $name): ColumnName
    {
        if ($name->name === 'field_ident') {
            return $this->lowering->names->columnName($name);
        }

        return $this->lowering->leaves->record(new ColumnName($this->lowering->names->identifier($name)));
    }

    /**
     * Lowers the position of an added or changed column: a node of `opt_place`; no position is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function position(Form $form, int $place): ?ColumnPosition
    {
        $position = $this->lowering->form($form->node($place));

        return match ($position->signature) {
            'opt_place:' => null,
            'opt_place: FIRST_SYM' => new ColumnPosition(null),
            'opt_place: AFTER_SYM ident' => new ColumnPosition($this->lowering->names->identifier($position->node(1))),
            default => throw ImplementationGap::production($position),
        };
    }

    /**
     * Lowers the ordering columns: a node of `alter_order_clause` or `alter_order_list`.
     *
     * @return list<OrderItem>
     * @throws ImplementationGap When a production has no rule
     */
    public function order(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'alter_order_clause: ORDER_SYM BY alter_order_list') {
            return $this->order($form->node(2));
        }
        if ($form->signature !== 'alter_order_list: alter_order_list , alter_order_item' && $form->signature !== 'alter_order_list: alter_order_item') {
            throw ImplementationGap::production($form);
        }
        $items = [];
        foreach ((new Lists())->items($list) as $item) {
            $order = $this->lowering->form($item);
            $direction = self::ORDER_ITEMS[$order->signature] ?? throw ImplementationGap::production($order);
            $items[] = new OrderItem($this->lowering->names->column($order->node(0)), $this->lowering->queries->direction($order->node($direction)));
        }

        return $items;
    }
}
