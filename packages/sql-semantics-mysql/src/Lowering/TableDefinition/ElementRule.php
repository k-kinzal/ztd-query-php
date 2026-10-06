<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column\ColumnRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\KeyRule;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key\ReferenceRule;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

/**
 * Lowers the element list of a table definition and its column elements.
 *
 * Rule: MYSQL-TABLE-ELEMENT-001. Scope: create_field_list, field_list,
 * field_list_item, table_element_list, table_element, column_def,
 * field_spec. The elements are kept in written order. Constructs:
 * ColumnDefinition, and the elements of MYSQL-KEY-DEFINITION-001.
 * Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ElementRule
{
    /**
     * The element list productions.
     */
    private const LISTS = [
        'field_list: field_list_item', 'field_list: field_list , field_list_item', 'table_element_list: table_element',
        'table_element_list: table_element_list , table_element',
    ];

    /**
     * The unit productions that pass an element on to their only child.
     */
    private const FORWARD = [
        'field_list_item: column_def' => true, 'field_list_item: key_def' => true, 'table_element: column_def' => true,
        'table_element: table_constraint_def' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an element list: a node of `create_field_list` or `table_element_list`.
     *
     * @return non-empty-list<TableElement>
     * @throws ImplementationGap When a production has no rule
     */
    public function elements(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'create_field_list: field_list') {
            $list = $form->node(0);
        } elseif ($list->name !== 'table_element_list') {
            throw ImplementationGap::production($form);
        }
        $elements = [];
        foreach ((new Spine($this->lowering))->items($list, self::LISTS, ['field_list_item', 'table_element']) as $item) {
            $elements[] = $this->element($item);
        }

        return $elements === [] ? throw ImplementationGap::production($form) : $elements;
    }

    /**
     * Lowers one element: a node of `field_list_item`, `table_element`, `column_def`, `key_def`, `table_constraint_def` or `field_spec`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function element(Node $element): TableElement
    {
        $form = $this->lowering->productions->form($element);
        if (isset(self::FORWARD[$form->signature])) {
            return $this->element($form->node(0));
        }
        if ($element->name === 'key_def' || $element->name === 'table_constraint_def') {
            return (new KeyRule($this->lowering))->element($element);
        }
        $references = new ReferenceRule($this->lowering);

        return match ($form->signature) {
            'column_def: ident field_def opt_references' => new ColumnDefinition(
                $this->lowering->leaves->record(new ColumnName($this->lowering->names->identifier($form->node(0)))),
                (new ColumnRule($this->lowering))->specification($form->node(1), null, $references->optional($form->node(2))),
            ),
            'column_def: field_spec opt_check_constraint' => $this->column($form->node(0), null, (new KeyRule($this->lowering))->optionalCheck($form->node(1))),
            'column_def: field_spec references' => $this->column($form->node(0), $references->references($form->node(1))),
            'field_spec: field_ident type opt_attribute', 'field_spec: field_ident field_def' => $this->column($element),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a 5.x column: a node of `field_spec`, with the reference or CHECK written after it.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function column(Node $spec, ?References $references = null, ?CheckConstraint $check = null): ColumnDefinition
    {
        $form = $this->lowering->productions->form($spec);
        $rule = new ColumnRule($this->lowering);
        $trailing = $check === null ? [] : [$check];

        return new ColumnDefinition($this->lowering->names->columnName($form->node(0)), match ($form->signature) {
            'field_spec: field_ident type opt_attribute' => $rule->specification($form->node(1), $form->node(2), $references, $trailing),
            'field_spec: field_ident field_def' => $rule->specification($form->node(1), null, $references, $trailing),
            default => throw ImplementationGap::production($form),
        });
    }
}
