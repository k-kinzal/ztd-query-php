<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\ConfigurationSetting;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\EstimateKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\ParallelSafety;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineEstimate;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineLanguage;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineOption;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineSource;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\SupportFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\Transforms;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers routine options and ALTER FUNCTION, PROCEDURE and ROUTINE.
 *
 * Rule: PG-ROUTINE-OPTION-LOWER-001. Scope: `opt_createfunc_opt_list`,
 * `createfunc_opt_list`, `createfunc_opt_item`, `common_func_opt_item`,
 * `func_as`, `transform_type_list`, `AlterFunctionStmt`,
 * `alterfunc_opt_list`, `opt_restrict`. Constructors: the routine options and
 * `AlterRoutine`. EXTERNAL before SECURITY and the trailing RESTRICT of
 * ALTER are noise words the server ignores. A configuration setting is
 * lowered by the utility family. Termination: lists are flattened iteratively.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html, https://www.postgresql.org/docs/17/sql-alterfunction.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class OptionRule
{
    /**
     * The attribute of each keyword-only option production.
     */
    private const ATTRIBUTES = [
        'common_func_opt_item: CALLED ON NULL_P INPUT_P' => RoutineAttribute::CalledOnNullInput,
        'common_func_opt_item: RETURNS NULL_P ON NULL_P INPUT_P' => RoutineAttribute::ReturnsNullOnNullInput,
        'common_func_opt_item: STRICT_P' => RoutineAttribute::Strict,
        'common_func_opt_item: IMMUTABLE' => RoutineAttribute::Immutable,
        'common_func_opt_item: STABLE' => RoutineAttribute::Stable,
        'common_func_opt_item: VOLATILE' => RoutineAttribute::Volatile,
        'common_func_opt_item: EXTERNAL SECURITY DEFINER' => RoutineAttribute::SecurityDefiner,
        'common_func_opt_item: EXTERNAL SECURITY INVOKER' => RoutineAttribute::SecurityInvoker,
        'common_func_opt_item: SECURITY DEFINER' => RoutineAttribute::SecurityDefiner,
        'common_func_opt_item: SECURITY INVOKER' => RoutineAttribute::SecurityInvoker,
        'common_func_opt_item: LEAKPROOF' => RoutineAttribute::Leakproof,
        'common_func_opt_item: NOT LEAKPROOF' => RoutineAttribute::NotLeakproof,
        'createfunc_opt_item: WINDOW' => RoutineAttribute::Window,
    ];

    /**
     * The kind each ALTER production alters.
     */
    private const ALTERED = [
        'AlterFunctionStmt: ALTER FUNCTION function_with_argtypes alterfunc_opt_list opt_restrict' => ObjectKind::Function,
        'AlterFunctionStmt: ALTER PROCEDURE function_with_argtypes alterfunc_opt_list opt_restrict' => ObjectKind::Procedure,
        'AlterFunctionStmt: ALTER ROUTINE function_with_argtypes alterfunc_opt_list opt_restrict' => ObjectKind::Routine,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `opt_createfunc_opt_list`; no option is an empty list.
     *
     * A definition (`AS`) is lowered last, with the language of the first
     * LANGUAGE option, wherever it is written.
     *
     * @return list<RoutineOption>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function options(Node $list): array
    {
        $form = $this->lowering->productions->form($list);
        if ($form->signature === 'opt_createfunc_opt_list:') {
            return [];
        }
        if ($form->signature !== 'opt_createfunc_opt_list: createfunc_opt_list') {
            throw ImplementationGap::production($form);
        }
        $items = $this->lowering->items($form->node(0), 'createfunc_opt_list: createfunc_opt_item', 'createfunc_opt_list: createfunc_opt_list createfunc_opt_item');
        $options = [];
        $language = null;
        foreach ($items as $index => $item) {
            $definition = $this->lowering->productions->form($item)->signature === 'createfunc_opt_item: AS func_as';
            $options[$index] = $definition ? null : $this->option($item);
            $language ??= $options[$index] instanceof RoutineLanguage ? $options[$index]->language : null;
        }
        $name = $language instanceof Word ? $language->word : ($language === null ? null : new Name($language->value));
        $read = [];
        foreach ($items as $index => $item) {
            $read[] = $options[$index] ?? $this->option($item, $name);
        }

        return $read;
    }

    /**
     * Lowers `createfunc_opt_item`; a definition is written in the given language.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $item, ?Name $language = null): RoutineOption
    {
        $form = $this->lowering->productions->form($item);
        if (isset(self::ATTRIBUTES[$form->signature])) {
            return self::ATTRIBUTES[$form->signature];
        }

        return match ($form->signature) {
            'createfunc_opt_item: AS func_as' => $this->definition($form->node(1), $language),
            'createfunc_opt_item: LANGUAGE NonReservedWord_or_Sconst' => new RoutineLanguage($this->lowering->options->wordOrString($form->node(1))),
            'createfunc_opt_item: TRANSFORM transform_type_list' => new Transforms($this->transforms($form->node(1))),
            'createfunc_opt_item: common_func_opt_item' => $this->common($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `common_func_opt_item`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function common(Node $item): RoutineOption
    {
        $form = $this->lowering->productions->form($item);
        if (isset(self::ATTRIBUTES[$form->signature])) {
            return self::ATTRIBUTES[$form->signature];
        }

        return match ($form->signature) {
            'common_func_opt_item: COST NumericOnly' => new RoutineEstimate(EstimateKind::Cost, $this->lowering->literals->signed($form->node(1))),
            'common_func_opt_item: ROWS NumericOnly' => new RoutineEstimate(EstimateKind::Rows, $this->lowering->literals->signed($form->node(1))),
            'common_func_opt_item: SUPPORT any_name' => new SupportFunction($this->lowering->names->dotted($form->node(1))),
            'common_func_opt_item: FunctionSetResetClause' => new ConfigurationSetting($this->lowering->utilities->setReset($form->node(0))),
            'common_func_opt_item: PARALLEL ColId' => new ParallelSafety($this->lowering->names->name($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `func_as`: the definition in the routine's language, null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function definition(Node $definition, ?Name $language): RoutineDefinition
    {
        $form = $this->lowering->productions->form($definition);
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'func_as: Sconst' => new RoutineDefinition(new RoutineSource($language, $literals->string($form->node(0)))),
            'func_as: Sconst , Sconst' => new RoutineDefinition(new RoutineSource($language, $literals->string($form->node(0))), $literals->string($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `transform_type_list`.
     *
     * @return list<\SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName>
     */
    public function transforms(Node $list): array
    {
        $types = [];
        foreach ($this->lowering->items($list, 'transform_type_list: FOR TYPE_P Typename', 'transform_type_list: transform_type_list , FOR TYPE_P Typename') as $type) {
            $types[] = $this->lowering->types->typeName($type);
        }

        return $types;
    }

    /**
     * Lowers `AlterFunctionStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alter(Node $statement): AlterRoutine
    {
        $form = $this->lowering->productions->form($statement);
        $kind = self::ALTERED[$form->signature] ?? throw ImplementationGap::production($form);
        $restrict = $this->lowering->productions->form($form->node(4));
        if ($restrict->signature !== 'opt_restrict: RESTRICT' && $restrict->signature !== 'opt_restrict:') {
            throw ImplementationGap::production($restrict);
        }
        $options = [];
        foreach ($this->lowering->items($form->node(3), 'alterfunc_opt_list: common_func_opt_item', 'alterfunc_opt_list: alterfunc_opt_list common_func_opt_item') as $item) {
            $options[] = $this->common($item);
        }

        return new AlterRoutine($kind, (new SignatureRule($this->lowering))->function($form->node(2)), $options);
    }
}
