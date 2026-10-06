<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnCheck;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Generated;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\GeneratedWhen;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Identity;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NullAllowed;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\References;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ColumnCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnCompression;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnStorage;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeOptionKind;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the elements of a table definition: column definitions, column options, column constraints and LIKE.
 *
 * Rule: PG-COLUMN-LOWER-001. Scope: `columnDef`, `columnOptions`,
 * `column_compression`, `opt_column_compression`, `column_storage`,
 * `opt_column_storage`, `ColQualList`, `ColConstraint`,
 * `ColConstraintElem`, `generated_when`, `ConstraintAttr`, `opt_no_inherit`,
 * `TableLikeClause`, `TableLikeOptionList`, `TableLikeOption`, and the
 * element lists of `CreateTableRule`. A constraint name is kept on its
 * constraint. WITH OPTIONS in `columnOptions` is noise (TableNoise).
 * Termination: lists are flattened iteratively. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ColumnRule
{
    /**
     * The attribute each `ConstraintAttr` production writes.
     */
    private const ATTRIBUTES = [
        'ConstraintAttr: DEFERRABLE' => ConstraintAttribute::Deferrable,
        'ConstraintAttr: NOT DEFERRABLE' => ConstraintAttribute::NotDeferrable,
        'ConstraintAttr: INITIALLY DEFERRED' => ConstraintAttribute::InitiallyDeferred,
        'ConstraintAttr: INITIALLY IMMEDIATE' => ConstraintAttribute::InitiallyImmediate,
    ];

    /**
     * The property each `TableLikeOption` production names.
     */
    private const LIKE = [
        'TableLikeOption: COMMENTS' => LikeOptionKind::Comments, 'TableLikeOption: COMPRESSION' => LikeOptionKind::Compression,
        'TableLikeOption: CONSTRAINTS' => LikeOptionKind::Constraints, 'TableLikeOption: DEFAULTS' => LikeOptionKind::Defaults,
        'TableLikeOption: IDENTITY_P' => LikeOptionKind::Identity, 'TableLikeOption: GENERATED' => LikeOptionKind::Generated,
        'TableLikeOption: INDEXES' => LikeOptionKind::Indexes, 'TableLikeOption: STATISTICS' => LikeOptionKind::Statistics,
        'TableLikeOption: STORAGE' => LikeOptionKind::Storage, 'TableLikeOption: ALL' => LikeOptionKind::All,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `OptTableElementList`.
     *
     * @return list<ColumnDefinition|LikeClause|Constraint>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function elements(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'OptTableElementList:') {
            return [];
        }
        if ($form->signature !== 'OptTableElementList: TableElementList') {
            throw ImplementationGap::production($form);
        }
        $elements = [];
        foreach ($this->lowering->items($form->node(0), 'TableElementList: TableElement', 'TableElementList: TableElementList , TableElement') as $item) {
            $element = $this->lowering->productions->form($item);
            $elements[] = match ($element->signature) {
                'TableElement: columnDef' => $this->column($element->node(0)),
                'TableElement: TableLikeClause' => $this->like($element->node(0)),
                'TableElement: TableConstraint' => $this->lowering->tables->tableConstraint($element->node(0)),
                default => throw ImplementationGap::production($element),
            };
        }

        return $elements;
    }

    /**
     * Lowers `OptTypedTableElementList`.
     *
     * @return list<Clause>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function typedElements(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'OptTypedTableElementList:') {
            return [];
        }
        if ($form->signature !== 'OptTypedTableElementList: ( TypedTableElementList )') {
            throw ImplementationGap::production($form);
        }
        $elements = [];
        foreach ($this->lowering->items($form->node(1), 'TypedTableElementList: TypedTableElement', 'TypedTableElementList: TypedTableElementList , TypedTableElement') as $item) {
            $element = $this->lowering->productions->form($item);
            $elements[] = match ($element->signature) {
                'TypedTableElement: columnOptions' => $this->options($element->node(0)),
                'TypedTableElement: TableConstraint' => $this->lowering->tables->tableConstraint($element->node(0)),
                default => throw ImplementationGap::production($element),
            };
        }

        return $elements;
    }

    /**
     * Lowers `columnDef`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function column(Node $definition): ColumnDefinition
    {
        $form = $this->lowering->productions->form($definition);
        if ($form->signature !== 'columnDef: ColId Typename opt_column_storage opt_column_compression create_generic_options ColQualList') {
            throw ImplementationGap::production($form);
        }

        return new ColumnDefinition(
            $this->lowering->names->name($form->node(0)),
            $this->lowering->types->typeName($form->node(1)),
            $this->storage($form->node(2)),
            $this->compression($form->node(3)),
            $this->lowering->options->genericOptions($form->node(4)),
            $this->qualifiers($form->node(5)),
        );
    }

    /**
     * Lowers `columnOptions`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $options): ColumnOptions
    {
        $form = $this->lowering->productions->form($options);

        return match ($form->signature) {
            'columnOptions: ColId ColQualList' => new ColumnOptions($this->lowering->names->name($form->node(0)), $this->qualifiers($form->node(1))),
            'columnOptions: ColId WITH OPTIONS ColQualList' => new ColumnOptions($this->lowering->names->name($form->node(0)), $this->qualifiers($form->node(3))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_column_storage` or `column_storage`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function storage(Node $clause): ?ColumnStorage
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_column_storage: column_storage' => $this->storage($form->node(0)),
            'opt_column_storage:' => null,
            'column_storage: STORAGE ColId' => new ColumnStorage($this->lowering->names->name($form->node(1))),
            'column_storage: STORAGE DEFAULT' => new ColumnStorage(null),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `opt_column_compression` or `column_compression`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function compression(Node $clause): ?ColumnCompression
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_column_compression: column_compression' => $this->compression($form->node(0)),
            'opt_column_compression:' => null,
            'column_compression: COMPRESSION ColId' => new ColumnCompression($this->lowering->names->name($form->node(1))),
            'column_compression: COMPRESSION DEFAULT' => new ColumnCompression(null),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ColQualList`.
     *
     * @return list<Clause>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function qualifiers(Node $list): array
    {
        $qualifiers = [];
        foreach ($this->lowering->items($list, 'ColQualList: ColQualList ColConstraint', 'ColQualList:') as $item) {
            $form = $this->lowering->productions->form($item);
            $qualifiers[] = match ($form->signature) {
                'ColConstraint: CONSTRAINT name ColConstraintElem' => $this->constraint($this->lowering->productions->form($form->node(2)), $this->lowering->names->name($form->node(1))),
                'ColConstraint: ColConstraintElem' => $this->constraint($this->lowering->productions->form($form->node(0)), null),
                'ColConstraint: ConstraintAttr' => $this->attribute($form->node(0)),
                'ColConstraint: COLLATE any_name' => new ColumnCollation($this->lowering->names->dotted($form->node(1))),
                default => throw ImplementationGap::production($form),
            };
        }

        return $qualifiers;
    }

    /**
     * Lowers `ColConstraintElem` with its name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constraint(Form $form, ?Name $name): Constraint
    {
        $constraints = new ConstraintRule($this->lowering);
        $options = $this->lowering->options;

        return match ($form->signature) {
            'ColConstraintElem: NOT NULL_P' => new NotNull($name),
            'ColConstraintElem: NULL_P' => new NullAllowed($name),
            'ColConstraintElem: UNIQUE opt_unique_null_treatment opt_definition OptConsTableSpace' => new ColumnUnique(
                $this->lowering->tables->uniqueNullTreatment($form->node(1)),
                $options->definitions($form->node(2)),
                $constraints->tablespace($form->node(3)),
                $name,
            ),
            'ColConstraintElem: PRIMARY KEY opt_definition OptConsTableSpace' => new ColumnPrimaryKey($options->definitions($form->node(2)), $constraints->tablespace($form->node(3)), $name),
            'ColConstraintElem: CHECK ( a_expr ) opt_no_inherit' => new ColumnCheck($this->lowering->expressions->expression($form->node(2)), $this->noInherit($form->node(4)), $name),
            'ColConstraintElem: DEFAULT b_expr' => new DefaultExpression($this->lowering->expressions->expression($form->node(1)), $name),
            'ColConstraintElem: GENERATED generated_when AS IDENTITY_P OptParenthesizedSeqOptList' => new Identity($this->when($form->node(1)), (new SequenceRule($this->lowering))->parenthesized($form->node(4)), $name),
            'ColConstraintElem: GENERATED generated_when AS ( a_expr ) STORED' => new Generated($this->when($form->node(1)), $this->lowering->expressions->expression($form->node(4)), $name),
            'ColConstraintElem: REFERENCES qualified_name opt_column_list key_match key_actions' => new References(
                $this->lowering->names->qualified($form->node(1)),
                $this->lowering->names->names($form->node(2)),
                $constraints->match($form->node(3)),
                $constraints->actions($form->node(4)),
                $name,
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `generated_when`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function when(Node $when): GeneratedWhen
    {
        $form = $this->lowering->productions->form($when);

        return match ($form->signature) {
            'generated_when: ALWAYS' => GeneratedWhen::Always,
            'generated_when: BY DEFAULT' => GeneratedWhen::ByDefault,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `ConstraintAttr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function attribute(Node $attribute): ConstraintAttribute
    {
        $form = $this->lowering->productions->form($attribute);

        return self::ATTRIBUTES[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers `opt_no_inherit`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function noInherit(Node $flag): bool
    {
        $form = $this->lowering->productions->form($flag);

        return match ($form->signature) {
            'opt_no_inherit: NO INHERIT' => true,
            'opt_no_inherit:' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `TableLikeClause` with its `TableLikeOptionList`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function like(Node $clause): LikeClause
    {
        $form = $this->lowering->productions->form($clause);
        if ($form->signature !== 'TableLikeClause: LIKE qualified_name TableLikeOptionList') {
            throw ImplementationGap::production($form);
        }
        $options = [];
        $list = $this->lowering->productions->form($form->node(2));
        while ($list->signature !== 'TableLikeOptionList:') {
            if ($list->signature !== 'TableLikeOptionList: TableLikeOptionList INCLUDING TableLikeOption' && $list->signature !== 'TableLikeOptionList: TableLikeOptionList EXCLUDING TableLikeOption') {
                throw ImplementationGap::production($list);
            }
            $option = $this->lowering->productions->form($list->node(2));
            $options[] = new LikeOption($list->token(1)->name === 'INCLUDING', self::LIKE[$option->signature] ?? throw ImplementationGap::production($option));
            $list = $this->lowering->productions->form($list->node(0));
        }

        return new LikeClause($this->lowering->names->qualified($form->node(1)), array_reverse($options));
    }
}
