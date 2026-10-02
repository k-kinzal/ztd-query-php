<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * The entry rules of the call family: the methods other families and the statement dispatcher call.
 *
 * Rule: MYSQL-CALL-ENTRY-001. Scope: function calls, aggregates, window functions and specifications, and
 * JSON_TABLE columns.
 * The method names, parameters and return types are fixed by the family
 * plan. A method delegates to the rule classes of this family; a method
 * the family has not implemented reports a missing rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/functions.html.
 * Status: Specified.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CallRules
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a function-like expression: a node of `function_call_keyword`, `function_call_nonkeyword`,
     * `function_call_generic`, `function_call_conflict`, `sum_expr`, `set_function_specification` or
     * `window_func_call`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function call(Node $call): Scalar
    {
        throw ImplementationGap::rule('MySQL call family: call');
    }

    /**
     * Lowers the current timestamp function of a column default or ON UPDATE clause: a node of `now`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function currentTimestamp(Node $now): Scalar
    {
        throw ImplementationGap::rule('MySQL call family: currentTimestamp');
    }

    /**
     * Lowers a window name: a node of `window_name`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function windowName(Node $name): Name
    {
        throw ImplementationGap::rule('MySQL call family: windowName');
    }

    /**
     * Lowers a window specification: a node of `window_spec`.
     *
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function windowSpecification(Node $specification): WindowSpecification
    {
        throw ImplementationGap::rule('MySQL call family: windowSpecification');
    }

    /**
     * Lowers the COLUMNS clause of JSON_TABLE: a node of `columns_clause`.
     *
     * @return list<JsonTableColumn>
     * @throws ImplementationGap Always, until the family is implemented
     */
    public function jsonTableColumns(Node $columns): array
    {
        throw ImplementationGap::rule('MySQL call family: jsonTableColumns');
    }
}
