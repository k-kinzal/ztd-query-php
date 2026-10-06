<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Column;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Spine;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\Column\FormatAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\StorageAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
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
 * ALWAYS is optional and not kept. The parser of MySQL 8.0 and later reads
 * every column attribute after a generation expression and then refuses
 * DEFAULT, ON UPDATE, AUTO_INCREMENT, SERIAL DEFAULT VALUE, COLUMN_FORMAT
 * and STORAGE there, and the type SERIAL, with ER_WRONG_USAGE ("Incorrect
 * usage of DEFAULT and generated column"; sql/parse_tree_column_attrs.h
 * `PT_default_column_attr::do_contextualize` and its siblings,
 * `PT_generated_field_def_base`); 5.7 has no grammar for them. Constructs: OrdinaryColumn,
 * GeneratedColumn, ParseGeneratedColumn. Terminates: lists are flattened
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
     * @throws AnalysisException When a generated column has an attribute or type the server refuses for it
     */
    public function specification(Node $definition, ?Node $attributes = null, ?References $references = null, array $trailing = []): ColumnSpecification
    {
        if ($definition->name === 'type') {
            return new OrdinaryColumn($this->lowering->types->type($definition), [...$this->attributes($attributes ?? throw ImplementationGap::rule('MySQL 5.6 column specification without attributes')), ...$trailing], $references);
        }
        $form = $this->lowering->productions->form($definition);
        $positions = self::GENERATED[$form->signature] ?? null;
        if ($positions !== null) {
            [$collation, $always, $expression, $storage, $list] = $positions;
            $this->always($form->node($always));
            $type = $this->lowering->types->type($form->node(0));
            $attributes = $this->attributes($form->node($list));
            $this->refuse($attributes, $type);

            return new GeneratedColumn(
                $type,
                $this->expression($form->node($expression)),
                $this->lowering->charsets->collation($form->node($collation)),
                $this->storage($form->node($storage)),
                [...$attributes, ...$trailing],
                $references,
            );
        }

        return match ($form->signature) {
            'field_def: type opt_attribute', 'field_def: type opt_column_attribute_list' => new OrdinaryColumn($this->lowering->types->type($form->node(0)), [...$this->attributes($form->node(1)), ...$trailing], $references),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an optional attribute list.
     *
     * @return list<ColumnAttribute>
     * @throws ImplementationGap When a production has no rule
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
            $attributes[] = $rule->attribute($item);
        }

        return $attributes;
    }

    /**
     * Refuses, as the server's parser does, the attributes and the type a generated column cannot have.
     *
     * The first such attribute in written order is named, then the type SERIAL.
     *
     * @param list<ColumnAttribute> $attributes The attributes in written order
     * @throws AnalysisException When the generated column has DEFAULT, ON UPDATE, AUTO_INCREMENT, SERIAL DEFAULT VALUE, COLUMN_FORMAT or STORAGE, or the type SERIAL
     */
    public function refuse(array $attributes, TypeName $type): void
    {
        foreach ($attributes as $attribute) {
            $word = match (true) {
                $attribute instanceof DefaultLiteral, $attribute instanceof DefaultExpression => 'DEFAULT',
                $attribute instanceof OnUpdate => 'ON UPDATE',
                $attribute instanceof KeywordAttribute && ($attribute->keyword === ColumnKeyword::AutoIncrement || $attribute->keyword === ColumnKeyword::SerialDefaultValue) => $attribute->keyword->value,
                $attribute instanceof FormatAttribute => 'COLUMN_FORMAT',
                $attribute instanceof StorageAttribute => 'STORAGE',
                default => null,
            };
            if ($word !== null) {
                throw new AnalysisException('Incorrect usage of ' . $word . ' and generated column');
            }
        }
        if ($type instanceof Elementary && $type->kind === ElementaryKind::Serial) {
            throw new AnalysisException('Incorrect usage of SERIAL and generated column');
        }
    }

    /**
     * Confirms the optional words GENERATED ALWAYS: a node of `opt_generated_always`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function always(Node $words): void
    {
        $form = $this->lowering->productions->form($words);
        if ($form->signature !== 'opt_generated_always:' && $form->signature !== 'opt_generated_always: GENERATED ALWAYS_SYM') {
            throw ImplementationGap::production($form);
        }
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
    public function expression(Node $expression): Scalar
    {
        if ($expression->name === 'expr') {
            return $this->lowering->expressions->expression($expression);
        }
        $form = $this->lowering->productions->form($expression);
        if ($form->signature !== 'generated_column_func: expr') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->expressions->expression($form->node(0));
    }

    /**
     * Lowers the internal 5.7 statement PARSE_GCOL_EXPR: a node of `parse_gcol_expr`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function parse(Node $statement): ParseGeneratedColumn
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'parse_gcol_expr: PARSE_GCOL_EXPR_SYM ( generated_column_func )') {
            throw ImplementationGap::production($form);
        }

        return new ParseGeneratedColumn($this->expression($form->node(2)));
    }
}
