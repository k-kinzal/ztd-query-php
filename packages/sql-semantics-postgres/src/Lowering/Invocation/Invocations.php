<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Invocation;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Argument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Statement\Node as StatementNode;
use SqlSemantics\Statement\Scalar;

/**
 * The entry point of the invocation family: function calls and function-like expressions.
 *
 * Rule: PG-INVOCATION-001 (stub — the invocation family implements the
 * bodies; the method signatures are the stable contract and a family may
 * narrow a return type). Scope: see `.agent/plan-pg.md`, family Invocation.
 * Status: Specified.
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
     * @throws ImplementationGap Until the family implements it
     */
    public function call(Node $call): Scalar
    {
        throw ImplementationGap::production($this->lowering->productions->form($call));
    }

    /**
     * Lowers call arguments: `func_arg_list` or `func_arg_list_opt`; no argument is an empty list.
     *
     * @return list<Argument>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function arguments(Node $arguments): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($arguments));
    }

    /**
     * Lowers `window_specification`.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function window(Node $specification): WindowSpecification
    {
        throw ImplementationGap::production($this->lowering->productions->form($specification));
    }

    /**
     * Lowers `xmlexists_argument`: the PASSING clause of XMLEXISTS and XMLTABLE.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function xmlPassing(Node $argument): Clause
    {
        throw ImplementationGap::production($this->lowering->productions->form($argument));
    }

    /**
     * Lowers `json_value_expr`: an expression with an optional FORMAT clause.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function jsonValue(Node $value): Clause
    {
        throw ImplementationGap::production($this->lowering->productions->form($value));
    }

    /**
     * Lowers `json_passing_clause_opt`; no clause is an empty list.
     *
     * @return list<Clause>
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function jsonPassing(Node $passing): array
    {
        throw ImplementationGap::production($this->lowering->productions->form($passing));
    }

    /**
     * Lowers `json_format_clause` or `json_format_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function jsonFormat(Node $format): ?StatementNode
    {
        throw ImplementationGap::production($this->lowering->productions->form($format));
    }

    /**
     * Lowers `json_behavior_clause_opt` or `json_on_error_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function jsonBehavior(Node $behavior): ?Clause
    {
        throw ImplementationGap::production($this->lowering->productions->form($behavior));
    }

    /**
     * Lowers `json_wrapper_behavior`; no clause is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function jsonWrapper(Node $wrapper): ?StatementNode
    {
        throw ImplementationGap::production($this->lowering->productions->form($wrapper));
    }

    /**
     * Lowers `json_quotes_clause_opt`; no clause is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function jsonQuotes(Node $quotes): ?StatementNode
    {
        throw ImplementationGap::production($this->lowering->productions->form($quotes));
    }

    /**
     * Lowers `json_key_uniqueness_constraint_opt`; no clause is null.
     *
     * @throws ImplementationGap Until the family implements it
     */
    public function jsonKeyUniqueness(Node $constraint): ?StatementNode
    {
        throw ImplementationGap::production($this->lowering->productions->form($constraint));
    }
}
