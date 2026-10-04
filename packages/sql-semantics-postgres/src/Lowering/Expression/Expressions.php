<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Expression;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayItems;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Statement\Scalar;

/**
 * The entry point of the expression family.
 *
 * Rule: PG-EXPRESSION-001. Scope: `a_expr`, `b_expr`, `c_expr`, `columnref`,
 * `indirection`, `indirection_el`, `opt_indirection`, `opt_slice_bound`,
 * `row`, `explicit_row`, `implicit_row`, `sub_type`, `subquery_Op`,
 * `expr_list`, `array_expr`, `array_expr_list`, `in_expr`, `case_expr`,
 * `when_clause_list`, `when_clause`, `case_default`, `case_arg`,
 * `json_predicate_type_constraint` and `opt_asymmetric`, through
 * PG-EXPRESSION-OPERATOR-001, PG-EXPRESSION-PREDICATE-001,
 * PG-EXPRESSION-PRIMARY-001 and PG-EXPRESSION-CONSTRUCTOR-001. A `b_expr` is
 * lowered into the same structures as an `a_expr`; the structures accept
 * only the operands the position allows (PG-PRECEDENCE-001). Function calls
 * are lowered by the invocation family and queries by the query family.
 * Termination: lists are flattened iteratively; operand nesting recurses on
 * the depth of the tree.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Expressions
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a scalar expression: `a_expr`, `b_expr` or `c_expr`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expression(Node $expression): Scalar
    {
        $form = $this->lowering->productions->form($expression);
        if ($form->signature === 'a_expr: c_expr' || $form->signature === 'b_expr: c_expr') {
            return $this->expression($form->node(0));
        }

        return match ($expression->name) {
            'a_expr', 'b_expr' => (new OperatorRule($this->lowering))->lower($form) ?? (new PredicateRule($this->lowering))->lower($form) ?? throw ImplementationGap::production($form),
            'c_expr' => (new PrimaryRule($this->lowering))->lower($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an expression list: `expr_list`.
     *
     * @return list<Scalar>
     */
    public function expressions(Node $list): array
    {
        $expressions = [];
        foreach ($this->lowering->items($list, 'expr_list: a_expr', 'expr_list: expr_list , a_expr') as $expression) {
            $expressions[] = $this->expression($expression);
        }

        return $expressions;
    }

    /**
     * Lowers a column reference: `columnref`.
     *
     * A dotted name is a `ColumnReference`; one ending in `.*` is a
     * `ColumnStar`; a subscript after the dotted name makes an `Indirection`
     * of it.
     */
    public function columnReference(Node $reference): Scalar
    {
        return (new PrimaryRule($this->lowering))->columnReference($reference);
    }

    /**
     * Lowers the steps applied to a value or an assignment target: `indirection` or `opt_indirection`; no step is an empty list.
     *
     * @return list<IndirectionStep>
     *
     * @throws ImplementationGap When a step has no rule
     */
    public function indirection(Node $indirection): array
    {
        return (new PrimaryRule($this->lowering))->indirection($indirection);
    }

    /**
     * Lowers one of the six comparison productions of `a_expr` or `b_expr`.
     */
    public function comparison(Form $form): BinaryOperation
    {
        return new BinaryOperation(new OperatorName($this->lowering->operators->symbol($form->token(1))), $this->expression($form->node(0)), $this->expression($form->node(2)));
    }

    /**
     * Lowers `c_expr: PARAM opt_indirection`: the parameter, with the steps applied to it.
     */
    public function parameter(Form $form): Scalar
    {
        return (new PrimaryRule($this->lowering))->parameter($form);
    }

    /**
     * Builds the negation of an operand written after a minus token, as `a_expr: - a_expr` does.
     *
     * Other families use it where the grammar writes a minus before a
     * constant outside an expression, such as `select_fetch_first_value`.
     */
    public function negation(Token $minus, Scalar $operand): Scalar
    {
        return new UnaryOperation(new OperatorName($this->lowering->operators->symbol($minus)), $operand);
    }

    /**
     * Lowers the fields of a row constructor: `row`, `explicit_row` or `implicit_row`.
     *
     * @return list<Scalar>
     */
    public function row(Node $row): array
    {
        return (new ConstructorRule($this->lowering))->row($row)->fields;
    }

    /**
     * Lowers a row constructor: `row`, `explicit_row` or `implicit_row`.
     */
    public function rowConstructor(Node $row): RowConstructor
    {
        return (new ConstructorRule($this->lowering))->row($row);
    }

    /**
     * Lowers a CASE expression: `case_expr`.
     */
    public function caseExpression(Node $case): CaseExpression
    {
        return (new ConstructorRule($this->lowering))->caseExpression($case);
    }

    /**
     * Lowers the bracketed items of an array constructor: `array_expr`.
     */
    public function arrayLiteral(Node $array): ArrayItems
    {
        return (new ConstructorRule($this->lowering))->items($array);
    }
}
