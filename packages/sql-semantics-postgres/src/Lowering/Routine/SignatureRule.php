<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Leaf\Keywords;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\DefaultSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ParameterMode;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers routine, aggregate and operator signatures and routine parameters.
 *
 * Rule: PG-SIGNATURE-LOWER-001. Scope: `function_with_argtypes`,
 * `function_with_argtypes_list`, `func_args`, `func_args_list`, `func_arg`,
 * `arg_class`, `aggregate_with_argtypes`, `aggregate_with_argtypes_list`,
 * `aggr_args`, `aggr_args_list`, `aggr_arg`, `operator_with_argtypes`,
 * `operator_with_argtypes_list`, `oper_argtypes`. Constructors:
 * `RoutineSignature`, `AggregateSignature`, `AggregateArguments`,
 * `OperatorSignature`, `FunctionParameter`. A routine named without
 * parentheses has no argument list; `()` is an empty one. A parameter keeps
 * the written order of its name and mode and the spelling of IN OUT. The
 * operand types of an operator keep how they are written, including the
 * single type the server rejects. Termination: lists are flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-dropfunction.html,
 * https://www.postgresql.org/docs/17/sql-dropaggregate.html, https://www.postgresql.org/docs/17/sql-dropoperator.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SignatureRule
{
    /**
     * The mode of each `arg_class` production.
     */
    private const MODES = [
        'arg_class: IN_P' => ParameterMode::In,
        'arg_class: OUT_P' => ParameterMode::Out,
        'arg_class: INOUT' => ParameterMode::InOut,
        'arg_class: IN_P OUT_P' => ParameterMode::InAndOut,
        'arg_class: VARIADIC' => ParameterMode::Variadic,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `function_with_argtypes`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function function(Node $signature): RoutineSignature
    {
        $form = $this->lowering->productions->form($signature);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'function_with_argtypes: func_name func_args' => new RoutineSignature($names->dotted($form->node(0)), $this->arguments($form->node(1))),
            'function_with_argtypes: type_func_name_keyword' => new RoutineSignature(new DottedName([$this->lowering->leaves->record(new Name((new Keywords($this->lowering))->word($form->node(0))))])),
            'function_with_argtypes: ColId' => new RoutineSignature(new DottedName([$names->name($form->node(0))])),
            'function_with_argtypes: ColId indirection' => new RoutineSignature(new DottedName([$names->name($form->node(0)), ...$names->fields($form->node(1))])),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `function_with_argtypes_list`.
     *
     * @return list<RoutineSignature>
     */
    public function functions(Node $list): array
    {
        $signatures = [];
        foreach ($this->lowering->items($list, 'function_with_argtypes_list: function_with_argtypes', 'function_with_argtypes_list: function_with_argtypes_list , function_with_argtypes') as $signature) {
            $signatures[] = $this->function($signature);
        }

        return $signatures;
    }

    /**
     * Lowers `func_args`.
     *
     * @return list<FunctionParameter>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function arguments(Node $arguments): array
    {
        $form = $this->lowering->productions->form($arguments);
        if ($form->signature === 'func_args: ( )') {
            return [];
        }
        if ($form->signature !== 'func_args: ( func_args_list )') {
            throw ImplementationGap::production($form);
        }
        $parameters = [];
        foreach ($this->lowering->items($form->node(1), 'func_args_list: func_arg', 'func_args_list: func_args_list , func_arg') as $argument) {
            $parameters[] = $this->parameter($argument);
        }

        return $parameters;
    }

    /**
     * Lowers `func_arg`, with the default value a `func_arg_with_default` adds.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parameter(Node $argument, ?Scalar $default = null, ?DefaultSpelling $spelling = null): FunctionParameter
    {
        $form = $this->lowering->productions->form($argument);
        $names = $this->lowering->names;
        $types = $this->lowering->types;

        return match ($form->signature) {
            'func_arg: arg_class param_name func_type' => new FunctionParameter($types->functionType($form->node(2)), $names->name($form->node(1)), $this->mode($form->node(0)), false, $default, $spelling),
            'func_arg: param_name arg_class func_type' => new FunctionParameter($types->functionType($form->node(2)), $names->name($form->node(0)), $this->mode($form->node(1)), true, $default, $spelling),
            'func_arg: param_name func_type' => new FunctionParameter($types->functionType($form->node(1)), $names->name($form->node(0)), null, false, $default, $spelling),
            'func_arg: arg_class func_type' => new FunctionParameter($types->functionType($form->node(1)), null, $this->mode($form->node(0)), false, $default, $spelling),
            'func_arg: func_type' => new FunctionParameter($types->functionType($form->node(0)), null, null, false, $default, $spelling),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `arg_class`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function mode(Node $mode): ParameterMode
    {
        $form = $this->lowering->productions->form($mode);

        return self::MODES[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers `aggregate_with_argtypes`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function aggregate(Node $signature): AggregateSignature
    {
        $form = $this->lowering->productions->form($signature);
        if ($form->signature !== 'aggregate_with_argtypes: func_name aggr_args') {
            throw ImplementationGap::production($form);
        }

        return new AggregateSignature($this->lowering->names->dotted($form->node(0)), $this->aggregateArguments($form->node(1)));
    }

    /**
     * Lowers `aggregate_with_argtypes_list`.
     *
     * @return list<AggregateSignature>
     */
    public function aggregates(Node $list): array
    {
        $signatures = [];
        foreach ($this->lowering->items($list, 'aggregate_with_argtypes_list: aggregate_with_argtypes', 'aggregate_with_argtypes_list: aggregate_with_argtypes_list , aggregate_with_argtypes') as $signature) {
            $signatures[] = $this->aggregate($signature);
        }

        return $signatures;
    }

    /**
     * Lowers `aggr_args`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function aggregateArguments(Node $arguments): AggregateArguments
    {
        $form = $this->lowering->productions->form($arguments);

        return match ($form->signature) {
            'aggr_args: ( * )' => new AggregateArguments([]),
            'aggr_args: ( aggr_args_list )' => new AggregateArguments($this->aggregateList($form->node(1))),
            'aggr_args: ( ORDER BY aggr_args_list )' => new AggregateArguments([], $this->aggregateList($form->node(3))),
            'aggr_args: ( aggr_args_list ORDER BY aggr_args_list )' => new AggregateArguments($this->aggregateList($form->node(1)), $this->aggregateList($form->node(4))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `aggr_args_list`.
     *
     * @return list<FunctionParameter>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function aggregateList(Node $list): array
    {
        $parameters = [];
        foreach ($this->lowering->items($list, 'aggr_args_list: aggr_arg', 'aggr_args_list: aggr_args_list , aggr_arg') as $argument) {
            $form = $this->lowering->productions->form($argument);
            if ($form->signature !== 'aggr_arg: func_arg') {
                throw ImplementationGap::production($form);
            }
            $parameters[] = $this->parameter($form->node(0));
        }

        return $parameters;
    }

    /**
     * Lowers `operator_with_argtypes`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function operator(Node $signature): OperatorSignature
    {
        $form = $this->lowering->productions->form($signature);
        if ($form->signature !== 'operator_with_argtypes: any_operator oper_argtypes') {
            throw ImplementationGap::production($form);
        }
        $operator = $this->lowering->operators->operator($form->node(0));
        $types = $this->lowering->productions->form($form->node(1));
        $typeNames = $this->lowering->types;

        return match ($types->signature) {
            'oper_argtypes: ( Typename )' => new OperatorSignature($operator, OperatorArity::Incomplete, [$typeNames->typeName($types->node(1))]),
            'oper_argtypes: ( Typename , Typename )' => new OperatorSignature($operator, OperatorArity::Binary, [$typeNames->typeName($types->node(1)), $typeNames->typeName($types->node(3))]),
            'oper_argtypes: ( NONE , Typename )' => new OperatorSignature($operator, OperatorArity::Prefix, [$typeNames->typeName($types->node(3))]),
            'oper_argtypes: ( Typename , NONE )' => new OperatorSignature($operator, OperatorArity::Postfix, [$typeNames->typeName($types->node(1))]),
            default => throw ImplementationGap::production($types),
        };
    }

    /**
     * Lowers `operator_with_argtypes_list`.
     *
     * @return list<OperatorSignature>
     */
    public function operators(Node $list): array
    {
        $signatures = [];
        foreach ($this->lowering->items($list, 'operator_with_argtypes_list: operator_with_argtypes', 'operator_with_argtypes_list: operator_with_argtypes_list , operator_with_argtypes') as $signature) {
            $signatures[] = $this->operator($signature);
        }

        return $signatures;
    }
}
