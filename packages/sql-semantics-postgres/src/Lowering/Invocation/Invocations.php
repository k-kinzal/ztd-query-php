<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQuoting;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapping;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlPassing;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * The entry point of the invocation family: function calls and function-like expressions.
 *
 * Rule: PG-INVOCATION-001. Scope: every nonterminal of the family (see
 * `.agent/plan-pg.md`, family Invocation): `func_expr`,
 * `func_expr_windowless`, `func_application` and their arguments,
 * aggregate ordering, FILTER, WITHIN GROUP, OVER and window specifications,
 * the SQL-syntax functions of `func_expr_common_subexpr`, the SQL/XML
 * functions and the SQL/JSON constructors, aggregates and query functions
 * with their clauses. Each method delegates to the rule of its nonterminal
 * group. Termination: lists are flattened iteratively; operands recurse on
 * the tree depth only. Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-FUNCTION-CALLS.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Invocations
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a call: `func_expr`, `func_expr_windowless` or `func_application`.
     *
     * @throws AnalysisException When WITHIN GROUP is combined with an ordering, DISTINCT or VARIADIC, which the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function call(Node $call): Scalar
    {
        return (new CallRule($this->lowering))->call($call);
    }

    /**
     * Lowers call arguments: `func_arg_list` or `func_arg_list_opt`; no argument is an empty list.
     *
     * @return list<Argument>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function arguments(Node $arguments): array
    {
        return (new CallRule($this->lowering))->arguments($arguments);
    }

    /**
     * Lowers `func_expr_common_subexpr`: the SQL-syntax functions.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function subexpression(Node $subexpression): Scalar
    {
        return (new SyntaxRule($this->lowering))->subexpression($subexpression);
    }

    /**
     * Lowers `window_specification`.
     *
     * @throws AnalysisException When the frame is one the server rejects while parsing
     */
    public function window(Node $specification): WindowSpecification
    {
        return (new WindowRule($this->lowering))->specification($specification);
    }

    /**
     * Lowers `over_clause`: a window specification, a window name, or nothing.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function over(Node $clause): WindowSpecification|Name|null
    {
        return (new WindowRule($this->lowering))->over($clause);
    }

    /**
     * Lowers `filter_clause`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function filter(Node $clause): ?Scalar
    {
        return (new CallRule($this->lowering))->filter($clause);
    }

    /**
     * Lowers `xmlexists_argument`: the PASSING clause of XMLEXISTS and XMLTABLE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function xmlPassing(Node $argument): XmlPassing
    {
        return (new XmlRule($this->lowering))->passing($argument);
    }

    /**
     * Lowers `xml_attribute_list`.
     *
     * @return list<XmlAttribute>
     */
    public function xmlAttributes(Node $list): array
    {
        return (new XmlRule($this->lowering))->attributes($list);
    }

    /**
     * Lowers `json_value_expr`: an expression with an optional FORMAT clause.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonValue(Node $value): JsonValueExpression
    {
        return (new JsonRule($this->lowering))->value($value);
    }

    /**
     * Lowers `json_passing_clause_opt`; no clause is an empty list.
     *
     * @return list<JsonArgument>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonPassing(Node $passing): array
    {
        return (new JsonQueryRule($this->lowering))->passing($passing);
    }

    /**
     * Lowers `json_format_clause` or `json_format_clause_opt`; no clause is null.
     *
     * @throws AnalysisException When the encoding is not UTF8, UTF16 or UTF32
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonFormat(Node $format): ?JsonFormat
    {
        return (new JsonRule($this->lowering))->format($format);
    }

    /**
     * Lowers `json_returning_clause_opt` or `json_output_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonReturning(Node $returning): ?JsonReturning
    {
        return (new JsonRule($this->lowering))->returning($returning);
    }

    /**
     * Lowers `json_name_and_value`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonKeyValue(Node $pair): JsonPair
    {
        return (new JsonRule($this->lowering))->keyValue($pair);
    }

    /**
     * Lowers `json_object_constructor_null_clause_opt` or `json_array_constructor_null_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonNulls(Node $clause): ?JsonNullHandling
    {
        return (new JsonRule($this->lowering))->nulls($clause);
    }

    /**
     * Lowers `json_behavior_clause_opt` or `json_on_error_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonBehavior(Node $behavior): ?JsonBehaviorClause
    {
        return (new JsonQueryRule($this->lowering))->behaviors($behavior);
    }

    /**
     * Lowers `json_wrapper_behavior`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonWrapper(Node $wrapper): ?JsonWrapping
    {
        return (new JsonQueryRule($this->lowering))->wrapper($wrapper);
    }

    /**
     * Lowers `json_quotes_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonQuotes(Node $quotes): ?JsonQuoting
    {
        return (new JsonQueryRule($this->lowering))->quotes($quotes);
    }

    /**
     * Lowers `json_key_uniqueness_constraint_opt`; no clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function jsonKeyUniqueness(Node $constraint): ?JsonUniqueKeys
    {
        return (new JsonRule($this->lowering))->uniqueness($constraint);
    }
}
