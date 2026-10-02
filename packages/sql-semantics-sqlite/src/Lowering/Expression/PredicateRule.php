<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Between;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTest;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\NullTestForm;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternMatch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\PatternOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InList;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\InTable;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the predicate productions: NULL tests, pattern matches, range tests and membership tests.
 *
 * Rule: SQLITE-EXPR-PREDICATE-001. Scope: the `expr` productions of ISNULL,
 * NOTNULL, NOT NULL, the pattern operators, BETWEEN and IN; likeop,
 * between_op, in_op. A written NOT is kept as the negation of the predicate.
 * Terminates: every operand is a strict subtree.
 * Source: https://sqlite.org/lang_expr.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PredicateRule
{
    private readonly CallRule $calls;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->calls = new CallRule($lowering);
    }

    /**
     * Lowers a predicate, or passes the form on to the function call rules.
     */
    public function expression(Form $form): Scalar
    {
        $expressions = $this->lowering->expressions;

        return match ($form->signature) {
            'expr: expr ISNULL|NOTNULL' => new NullTest($expressions->expression($form->node(0)), $form->token(1)->name === 'ISNULL' ? NullTestForm::IsNull : NullTestForm::NotNull),
            'expr: expr NOT NULL' => new NullTest($expressions->expression($form->node(0)), NullTestForm::NotNullWords),
            'expr: expr likeop expr' => $this->match($form, null),
            'expr: expr likeop expr ESCAPE expr' => $this->match($form, $form->node(4)),
            'expr: expr between_op expr AND expr' => new Between($expressions->expression($form->node(0)), $expressions->expression($form->node(2)), $expressions->expression($form->node(4)), $this->negated($form->node(1))),
            'expr: expr in_op LP exprlist RP' => new InList($expressions->expression($form->node(0)), $expressions->list($form->node(3)), $this->negated($form->node(1))),
            'expr: expr in_op LP select RP' => new InQuery($expressions->expression($form->node(0)), $this->lowering->selects->select($form->node(3)), $this->negated($form->node(1))),
            'expr: expr in_op nm dbnm paren_exprlist' => new InTable($expressions->expression($form->node(0)), $this->lowering->names->scoped($form->node(2), $form->node(3)), $expressions->optionalList($form->node(4)), $this->negated($form->node(1))),
            default => $this->calls->expression($form),
        };
    }

    /**
     * Lowers a pattern match with its optional escape operand.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function match(Form $form, ?Node $escape): PatternMatch
    {
        $expressions = $this->lowering->expressions;
        $operator = $this->lowering->productions->form($form->node(1));
        $token = match ($operator->signature) {
            'likeop: LIKE_KW|MATCH' => $operator->token(0),
            'likeop: NOT LIKE_KW|MATCH' => $operator->token(1),
            default => throw ImplementationGap::production($operator),
        };

        return new PatternMatch(
            PatternOperator::from(strtoupper($token->text)),
            $expressions->expression($form->node(0)),
            $expressions->expression($form->node(2)),
            $operator->signature === 'likeop: NOT LIKE_KW|MATCH',
            $escape === null ? null : $expressions->expression($escape),
        );
    }

    /**
     * Tells whether a BETWEEN or IN operator is written with NOT.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function negated(Node $operator): bool
    {
        $form = $this->lowering->productions->form($operator);

        return match ($form->signature) {
            'between_op: BETWEEN', 'in_op: IN' => false,
            'between_op: NOT BETWEEN', 'in_op: NOT IN' => true,
            default => throw ImplementationGap::production($form),
        };
    }
}
