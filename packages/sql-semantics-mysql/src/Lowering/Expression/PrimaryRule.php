<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the simple_expr level: names, literals, parameters, variables, calls and the prefix and postfix operators.
 *
 * Rule: MYSQL-SIMPLE-EXPR-001. Scope: simple_expr, not2. COLLATE and the
 * concatenation `||` of PIPES_AS_CONCAT are left recursive; the left spine
 * is walked in a loop. A function-like form is handed to the call family,
 * a name, literal, parameter or variable to the leaf rules, and the
 * bracketed, subquery and keyword forms to MYSQL-SIMPLE-FORM-001 and
 * MYSQL-CONSTRUCT-001. Under HIGH_NOT_PRECEDENCE the keyword NOT is the
 * tight negation `!`. Constructs: Collated, Concatenation, Unary.
 * Terminates: the spine loop descends one left child per step; every
 * other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PrimaryRule
{
    /**
     * The productions whose only child is a function-like expression.
     */
    private const CALLS = [
        'simple_expr: function_call_keyword' => true, 'simple_expr: function_call_nonkeyword' => true, 'simple_expr: function_call_generic' => true,
        'simple_expr: function_call_conflict' => true, 'simple_expr: sum_expr' => true, 'simple_expr: set_function_specification' => true,
        'simple_expr: window_func_call' => true,
    ];

    /**
     * The productions whose only child is a leaf: the leaf kind.
     */
    private const LEAVES = [
        'simple_expr: simple_ident' => 'column', 'simple_expr: literal' => 'literal', 'simple_expr: literal_or_null' => 'literal',
        'simple_expr: param_marker' => 'parameter', 'simple_expr: variable' => 'variable', 'simple_expr: rvalue_system_or_user_variable' => 'variable',
        'simple_expr: in_expression_user_variable_assignment' => 'variable',
    ];

    /**
     * The prefix operator productions.
     */
    private const PREFIX = [
        'simple_expr: + simple_expr' => UnaryOperator::Plus, 'simple_expr: - simple_expr' => UnaryOperator::Minus,
        'simple_expr: ~ simple_expr' => UnaryOperator::Invert, 'simple_expr: not2 simple_expr' => UnaryOperator::Not,
    ];

    /**
     * The left-recursive productions.
     */
    private const POSTFIX = ['simple_expr: simple_expr COLLATE_SYM ident_or_text' => true, 'simple_expr: simple_expr OR_OR_SYM simple_expr' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of simple_expr.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function simpleExpression(Node $expression): Scalar
    {
        $pending = [];
        $form = $this->lowering->form($expression);
        while (isset(self::POSTFIX[$form->signature])) {
            $pending[] = $form;
            $form = $this->lowering->form($form->node(0));
        }
        $result = $this->unit($form);
        foreach (array_reverse($pending) as $operation) {
            $result = $operation->signature === 'simple_expr: simple_expr OR_OR_SYM simple_expr'
                ? new Concatenation($result, $this->simpleExpression($operation->node(2)))
                : new Collated($result, $this->lowering->names->identifier($operation->node(2)));
        }

        return $result;
    }

    /**
     * Lowers a simple_expr production that is not left recursive.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function unit(Form $form): Scalar
    {
        if (isset(self::CALLS[$form->signature])) {
            return $this->lowering->calls->call($form->node(0));
        }
        if (isset(self::LEAVES[$form->signature])) {
            return $this->leaf(self::LEAVES[$form->signature], $form->node(0));
        }
        $prefix = self::PREFIX[$form->signature] ?? null;
        if ($prefix !== null) {
            if ($prefix === UnaryOperator::Not) {
                $this->negation($form->node(0));
            }

            return new Unary($prefix, $this->simpleExpression($form->node(1)));
        }

        return (new FormRule($this->lowering))->lower($form);
    }

    /**
     * Lowers a leaf of a kind.
     */
    public function leaf(string $kind, Node $leaf): Scalar
    {
        return match ($kind) {
            'column' => $this->lowering->names->column($leaf),
            'literal' => $this->lowering->literals->literal($leaf),
            'parameter' => $this->lowering->literals->parameter($leaf),
            default => $this->lowering->variables->variable($leaf),
        };
    }

    /**
     * Confirms the tight negation keyword: `!`, or NOT under HIGH_NOT_PRECEDENCE.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function negation(Node $keyword): void
    {
        $form = $this->lowering->form($keyword);
        if ($form->signature !== 'not2: !' && $form->signature !== 'not2: NOT2_SYM') {
            throw ImplementationGap::production($form);
        }
    }
}
