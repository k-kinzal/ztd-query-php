<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound;
use SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ColumnDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers type names.
 *
 * Rule: PG-TYPE-NAME-LOWER-001. Scope: `Typename`, `opt_array_bounds`,
 * `func_type`, `type_list`, `type_name_list`, `TableFuncElement`,
 * `TableFuncElementList`, `OptTableFuncElementList`, and the type of a typed
 * constant in `AexprConst`; the entry points for `SimpleTypename` and for
 * an interval type written outside a type name delegate to the designation
 * rules. Constructors: `TypeName`, `ArraySpecifier`,
 * `ArrayBound`, `TypedColumn`, `ColumnDesignation`. The grammar action
 * rejects a named argument or an ORDER BY among the modifiers of a typed
 * constant, so they are SQL outside the grammar. Termination: lists and array
 * bounds are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/datatype.html, https://www.postgresql.org/docs/17/arrays.html#ARRAYS-DECLARATION.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TypeNames
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `Typename`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function typeName(Node $type): TypeName
    {
        $form = $this->lowering->productions->form($type);
        $designations = new Designations($this->lowering);
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'Typename: SimpleTypename opt_array_bounds' => new TypeName($designations->simple($form->node(0)), false, $this->bounds($form->node(1))),
            'Typename: SETOF SimpleTypename opt_array_bounds' => new TypeName($designations->simple($form->node(1)), true, $this->bounds($form->node(2))),
            'Typename: SimpleTypename ARRAY [ Iconst ]' => new TypeName($designations->simple($form->node(0)), false, new ArraySpecifier([new ArrayBound($literals->integer($form->node(3)))], true)),
            'Typename: SETOF SimpleTypename ARRAY [ Iconst ]' => new TypeName($designations->simple($form->node(1)), true, new ArraySpecifier([new ArrayBound($literals->integer($form->node(4)))], true)),
            'Typename: SimpleTypename ARRAY' => new TypeName($designations->simple($form->node(0)), false, new ArraySpecifier([], true)),
            'Typename: SETOF SimpleTypename ARRAY' => new TypeName($designations->simple($form->node(1)), true, new ArraySpecifier([], true)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `SimpleTypename`: a type without SETOF or an array part, as written after AS in a sequence option or in XMLSERIALIZE.
     */
    public function simple(Node $type): TypeDesignation
    {
        return (new Designations($this->lowering))->simple($type);
    }

    /**
     * Lowers `ConstInterval opt_interval`: the interval type with an optional field restriction, as written in a zone value or a typed constant.
     */
    public function interval(Node $keyword, Node $restriction): IntervalDesignation
    {
        return (new Intervals($this->lowering))->interval($keyword, $restriction);
    }

    /**
     * Lowers `ConstInterval ( Iconst )`: the interval type with a seconds precision.
     */
    public function preciseInterval(Node $keyword, Node $precision): IntervalDesignation
    {
        return (new Intervals($this->lowering))->precise($keyword, $precision);
    }

    /**
     * Lowers `opt_array_bounds`; no bracket pair is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function bounds(Node $bounds): ?ArraySpecifier
    {
        $dimensions = [];
        $current = $bounds;
        while (true) {
            $form = $this->lowering->productions->form($current);
            if ($form->signature === 'opt_array_bounds:') {
                return $dimensions === [] ? null : new ArraySpecifier(array_reverse($dimensions));
            }
            $dimensions[] = match ($form->signature) {
                'opt_array_bounds: opt_array_bounds [ ]' => new ArrayBound(),
                'opt_array_bounds: opt_array_bounds [ Iconst ]' => new ArrayBound($this->lowering->literals->integer($form->node(2))),
                default => throw ImplementationGap::production($form),
            };
            $current = $form->node(0);
        }
    }

    /**
     * Lowers `func_type`: a type name, or the type of a column written `table.column%TYPE`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function functionType(Node $type): TypeName
    {
        $form = $this->lowering->productions->form($type);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'func_type: Typename' => $this->typeName($form->node(0)),
            'func_type: type_function_name attrs % TYPE_P' => new TypeName(new ColumnDesignation(new DottedName([$names->name($form->node(0)), ...$names->attributes($form->node(1))]))),
            'func_type: SETOF type_function_name attrs % TYPE_P' => new TypeName(new ColumnDesignation(new DottedName([$names->name($form->node(1)), ...$names->attributes($form->node(2))])), true),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `type_list` or `type_name_list`.
     *
     * @return list<TypeName>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function typeNames(Node $list): array
    {
        $spine = match ($list->name) {
            'type_list' => ['type_list: Typename', 'type_list: type_list , Typename'],
            'type_name_list' => ['type_name_list: Typename', 'type_name_list: type_name_list , Typename'],
            default => throw ImplementationGap::production($this->lowering->productions->form($list)),
        };
        $types = [];
        foreach ($this->lowering->items($list, ...$spine) as $type) {
            $types[] = $this->typeName($type);
        }

        return $types;
    }

    /**
     * Lowers `OptTableFuncElementList` or `TableFuncElementList`; an absent list is empty.
     *
     * @return list<TypedColumn>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function typedColumns(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'OptTableFuncElementList:') {
            return [];
        }
        $columns = [];
        $elements = $form->signature === 'OptTableFuncElementList: TableFuncElementList' ? $form->node(0) : $list;
        foreach ($this->lowering->items($elements, 'TableFuncElementList: TableFuncElement', 'TableFuncElementList: TableFuncElementList , TableFuncElement') as $element) {
            $columns[] = $this->typedColumn($element);
        }

        return $columns;
    }

    /**
     * Lowers `TableFuncElement`: a column name, its type and an optional collation.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function typedColumn(Node $element): TypedColumn
    {
        $column = $this->lowering->productions->form($element);
        if ($column->signature !== 'TableFuncElement: ColId Typename opt_collate_clause') {
            throw ImplementationGap::production($column);
        }

        return new TypedColumn($this->lowering->names->name($column->node(0)), $this->typeName($column->node(1)), $this->lowering->names->optionalDotted($column->node(2)));
    }

    /**
     * Lowers the type of a typed constant from its `AexprConst` production.
     *
     * @throws AnalysisException When a modifier is passed by name or the modifier list has an ORDER BY, which the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function constantType(Form $constant): TypeName
    {
        $designations = new Designations($this->lowering);
        $intervals = new Intervals($this->lowering);

        return new TypeName(match ($constant->signature) {
            'AexprConst: func_name Sconst' => new NamedDesignation($this->lowering->names->dotted($constant->node(0))),
            'AexprConst: func_name ( func_arg_list opt_sort_clause ) Sconst' => new NamedDesignation($this->lowering->names->dotted($constant->node(0)), $this->modifiers($constant)),
            'AexprConst: ConstTypename Sconst' => $designations->constant($constant->node(0)),
            'AexprConst: ConstInterval Sconst opt_interval' => $intervals->interval($constant->node(0), $constant->node(2)),
            'AexprConst: ConstInterval ( Iconst ) Sconst' => $intervals->precise($constant->node(0), $constant->node(2)),
            default => throw ImplementationGap::production($constant),
        });
    }

    /**
     * Lowers the modifiers of a typed constant written like a function call.
     *
     * @return list<Scalar>
     *
     * @throws AnalysisException When a modifier is passed by name or an ORDER BY is written
     */
    public function modifiers(Form $constant): array
    {
        $modifiers = [];
        foreach ($this->lowering->invocations->arguments($constant->node(2)) as $argument) {
            if ($argument->name() !== null) {
                throw new AnalysisException('type modifier cannot have parameter name');
            }
            $modifiers[] = $argument->value();
        }
        if ($this->lowering->queries->sortClause($constant->node(3)) !== []) {
            throw new AnalysisException('type modifier cannot have ORDER BY');
        }

        return $modifiers;
    }
}
