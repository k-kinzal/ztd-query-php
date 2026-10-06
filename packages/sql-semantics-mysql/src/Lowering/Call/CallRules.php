<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * The entry rules of the call family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-CALL-ENTRY-001. Scope: function calls, aggregates, window functions and specifications, and
 * JSON_TABLE columns. A function-like expression is handed to the rule of
 * its production: FunctionRule (keyword functions, CHAR, TRIM, POSITION and
 * calls written as `name(...)`), TemporalRule (clock functions and the
 * temporal special syntaxes), WeightRule (WEIGHT_STRING), JsonRule
 * (JSON_VALUE and the JSON_TABLE columns), AggregateRule (set functions)
 * and WindowRule (window functions and windows). The unit productions
 * function_call_conflict: geometry_function and the two alternatives of
 * set_function_specification forward to their only child.
 * The method names, parameters and return types are fixed by the family
 * plan. Terminates: each method lowers a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CallRules
{
    private readonly FrameRule $frames;

    private readonly WindowRule $windows;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
        $this->frames = new FrameRule($lowering);
        $this->windows = new WindowRule($lowering, $this->frames);
    }

    /**
     * Lowers a function-like expression: a node of `function_call_keyword`, `function_call_nonkeyword`,
     * `function_call_generic`, `function_call_conflict`, `sum_expr`, `set_function_specification` or
     * `window_func_call`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function call(Node $call): Scalar
    {
        $form = $this->lowering->form($call);
        if (in_array($form->signature, ['function_call_conflict: geometry_function', 'set_function_specification: sum_expr', 'set_function_specification: grouping_operation'], true)) {
            return $this->call($form->node(0));
        }
        if ($call->name === 'sum_expr') {
            return (new AggregateRule($this->lowering, $this->windows))->aggregate($call);
        }
        if ($call->name === 'window_func_call') {
            return $this->windows->function($call);
        }

        return $this->function($form);
    }

    /**
     * Lowers a function call production of function_call_keyword, function_call_nonkeyword, function_call_conflict,
     * geometry_function, grouping_operation or function_call_generic.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function function(Form $form): Scalar
    {
        $functions = new FunctionRule($this->lowering);
        if ($functions->claims($form->signature)) {
            return $functions->call($form);
        }
        $temporal = new TemporalRule($this->lowering);
        if ($temporal->claims($form->signature)) {
            return $temporal->call($form);
        }
        $weights = new WeightRule($this->lowering);
        if ($weights->claims($form->signature)) {
            return $weights->call($form);
        }
        if ($form->signature === 'function_call_keyword: JSON_VALUE_SYM ( simple_expr , text_literal opt_returning_type opt_on_empty_or_error )') {
            return (new JsonRule($this->lowering))->value($form);
        }

        throw ImplementationGap::production($form);
    }

    /**
     * Lowers the current timestamp function of a column default or ON UPDATE clause: a node of `now`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function currentTimestamp(Node $now): Scalar
    {
        return (new TemporalRule($this->lowering))->currentTimestamp($now);
    }

    /**
     * Lowers a window name: a node of `window_name`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function windowName(Node $name): Name
    {
        return $this->windows->name($name);
    }

    /**
     * Lowers a window specification: a node of `window_spec`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function windowSpecification(Node $specification): WindowSpecification
    {
        return $this->frames->specification($specification);
    }

    /**
     * Lowers the COLUMNS clause of JSON_TABLE: a node of `columns_clause`.
     *
     * @return list<JsonTableColumn>
     * @throws ImplementationGap When a production has no rule
     */
    public function jsonTableColumns(Node $columns): array
    {
        return (new JsonRule($this->lowering))->columns($columns);
    }
}
