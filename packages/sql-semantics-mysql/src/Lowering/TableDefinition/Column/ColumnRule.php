<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Spine;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers column specifications: the data type, the attributes and the generation expression of a column.
 *
 * Rule: MYSQL-COLUMN-SPECIFICATION-001. Scope: field_def, opt_attribute,
 * opt_attribute_list, opt_column_attribute_list, column_attribute_list,
 * opt_gcol_attribute_list, gcol_attribute_list, opt_generated_always,
 * opt_stored_attribute, generated_column_func, parse_gcol_expr. MySQL 5.6
 * writes the type and the attributes directly in field_spec. A CHECK clause
 * after a 5.x column definition is the last attribute; an inline
 * REFERENCES clause is the reference of the specification. GENERATED
 * ALWAYS is optional and retained on the generation specification. The parser of MySQL 8.0 and later reads
 * every column attribute after a generation expression and then refuses
 * DEFAULT, ON UPDATE, AUTO_INCREMENT, SERIAL DEFAULT VALUE, COLUMN_FORMAT
 * and STORAGE there, and the type SERIAL, with ER_WRONG_USAGE ("Incorrect
 * usage of DEFAULT and generated column"; sql/parse_tree_column_attrs.h
 * `PT_default_column_attr::do_contextualize` and its siblings,
 * `PT_generated_field_def_base`); 5.7 has no grammar for them. Constructs: OrdinaryColumn,
 * GeneratedColumn. Terminates: lists are flattened
 * iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ColumnRule
{
    /**
     * The attribute list productions.
     */
    private const LISTS = [
        'opt_attribute_list: opt_attribute_list attribute', 'opt_attribute_list: attribute', 'column_attribute_list: column_attribute_list column_attribute',
        'column_attribute_list: column_attribute', 'gcol_attribute_list: gcol_attribute_list gcol_attribute', 'gcol_attribute_list: gcol_attribute',
    ];

    /**
     * The optional attribute list productions: empty, or the list.
     */
    private const OPTIONAL = [
        'opt_attribute:' => false, 'opt_attribute: opt_attribute_list' => true, 'opt_column_attribute_list:' => false,
        'opt_column_attribute_list: column_attribute_list' => true, 'opt_gcol_attribute_list:' => false, 'opt_gcol_attribute_list: gcol_attribute_list' => true,
    ];

    /**
     * The generated column productions: the positions of the collation, the expression, the storage and the attributes.
     */
    private const GENERATED = [
        'field_def: type opt_collate_explicit opt_generated_always AS ( generated_column_func ) opt_stored_attribute opt_gcol_attribute_list' => [1, 2, 5, 7, 8],
        'field_def: type opt_collate opt_generated_always AS ( expr ) opt_stored_attribute opt_column_attribute_list' => [1, 2, 5, 7, 8],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a column specification: a node of `field_def`, or the nodes of `type` and `opt_attribute` (5.6).
     *
     * @param list<ColumnAttribute> $trailing Attributes written after the specification, such as a 5.x column CHECK
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When column operands contradict their type
     */
    public function specification(Node $definition, ?Node $attributes = null, ?References $references = null, array $trailing = [], ?\SqlSemantics\Statement\Identifier\Name $name = null): ColumnSpecification
    {
        if ($definition->name === 'type') {
            return $this->ordinary($definition, $attributes ?? throw ImplementationGap::rule('MySQL 5.6 column specification without attributes'), $references, $trailing, $name);
        }
        $form = $this->lowering->productions->form($definition);
        $positions = self::GENERATED[$form->signature] ?? null;
        if ($positions !== null) {
            [$collation, $always, $expression, $storage, $list] = $positions;
            $written = $this->always($form->node($always));
            $type = $this->lowering->types->type($form->node(0), $name->value ?? '');
            $attributes = $this->attributes($form->node($list));
            $value = $this->expression($form->node($expression), $name);
            if (($this->lowering->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5744 && (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacyExpressions())->contains($value)) || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\GroupedOperands())->deferred($value)) {
                return new \SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn($type, $value, $this->lowering->charsets->collation($form->node($collation)), $this->storage($form->node($storage)), [...$attributes, ...$trailing], $references, generatedAlways: $written);
            }
            return new GeneratedColumn(
                $type,
                $value,
                $this->lowering->charsets->collation($form->node($collation)),
                $this->storage($form->node($storage)),
                [...$attributes, ...$trailing],
                $references,
                generatedAlways: $written,
            );
        }

        return match ($form->signature) {
            'field_def: type opt_attribute', 'field_def: type opt_column_attribute_list' => $this->ordinary($form->node(0), $form->node(1), $references, $trailing, $name),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Retains the declared type and ordered attributes, including incompatible requests.
     *
     * @param list<ColumnAttribute> $trailing Attributes after the type's own list
     * @throws \SqlSemantics\Construction\RejectedInput When column operands contradict their type
     */
    public function ordinary(Node $definition, Node $list, ?References $references, array $trailing, ?\SqlSemantics\Statement\Identifier\Name $name = null): OrdinaryColumn
    {
        $type = $this->lowering->types->type($definition, $name->value ?? '');
        $attributes = [...$this->attributes($list), ...$trailing];
        return new OrdinaryColumn($type, $attributes, $references);
    }

    /**
     * Lowers an optional attribute list.
     *
     * @return list<ColumnAttribute>
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When enforcement has no preceding CHECK constraint
     */
    public function attributes(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if (!(self::OPTIONAL[$form->signature] ?? throw ImplementationGap::production($form))) {
            return [];
        }
        $rule = new AttributeRule($this->lowering);
        $attributes = [];
        foreach ((new Spine($this->lowering))->items($form->node(0), self::LISTS, ['attribute', 'column_attribute', 'gcol_attribute']) as $item) {
            $attribute = $rule->attribute($item);
            $attributes[] = $attribute;
            if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnAttributes())->enforcement($attributes) !== null) {
                $first = $item->tokens()[0];
                $offset = $first->offset - ($attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute && $attribute->enforced ? strlen($first->leading) : 0);
                throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\UnattachedEnforcement($offset), $offset, true);
            }
        }

        return $attributes;
    }

    /**
     * Confirms the optional words GENERATED ALWAYS: a node of `opt_generated_always`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function always(Node $words): \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords
    {
        $form = $this->lowering->productions->form($words);
        if ($form->signature !== 'opt_generated_always:' && $form->signature !== 'opt_generated_always: GENERATED ALWAYS_SYM') {
            throw ImplementationGap::production($form);
        }
        return $form->signature === 'opt_generated_always:' ? \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted : \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written;
    }

    /**
     * Lowers VIRTUAL or STORED: a node of `opt_stored_attribute`; absent is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function storage(Node $storage): ?GeneratedStorage
    {
        $form = $this->lowering->productions->form($storage);

        return match ($form->signature) {
            'opt_stored_attribute:' => null,
            'opt_stored_attribute: VIRTUAL_SYM' => GeneratedStorage::Virtual,
            'opt_stored_attribute: STORED_SYM' => GeneratedStorage::Stored,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a generation expression: a node of `generated_column_func` or `expr`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function expression(Node $expression, ?\SqlSemantics\Statement\Identifier\Name $column = null): Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand
    {
        if ($expression->name !== 'expr') {
            $form = $this->lowering->productions->form($expression);
            if ($form->signature !== 'generated_column_func: expr') {
                throw ImplementationGap::production($form);
            }
            $expression = $form->node(0);
        }
        return $this->lowering->expressions->operand($expression);
    }

    /**
     * Refuses the internal 5.7 PARSE_GCOL_EXPR entry before publishing a statement.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When the internal entry is submitted as SQL
     */
    public function parse(Node $statement): never
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'parse_gcol_expr: PARSE_GCOL_EXPR_SYM ( generated_column_func )') {
            throw ImplementationGap::production($form);
        }

        throw (new \SqlSemantics\Platform\MySql\Lowering\ClientEntry())->generated($statement) ?? ImplementationGap::production($form);
    }
}
