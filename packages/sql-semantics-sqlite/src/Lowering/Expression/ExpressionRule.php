<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\IntegerLiteral;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers expression productions into scalar structure.
 *
 * Rule: SQLITE-EXPR-001. Scope: expr, term. Operands keep their written order
 * and every parenthesis pair is kept as a grouping, so the structure states
 * the evaluation order the parser chose. Terminates: every operand is a strict
 * subtree. Source: https://sqlite.org/lang_expr.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one expression.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expression(Node $expression): Scalar
    {
        $form = $this->lowering->productions->form($expression);

        return match ($form->signature) {
            'expr: term' => $this->term($form->node(0)),
            'expr: LP expr RP' => new Grouped($this->expression($form->node(1))),
            'expr: idj' => new ColumnUse($this->lowering->names->token($form->token(0))),
            'expr: nm DOT nm' => new ColumnUse($this->lowering->names->name($form->node(2)), new QualifiedName($this->lowering->names->name($form->node(0)))),
            'expr: expr AND expr' => new Binary(BinaryOperator::And, $this->expression($form->node(0)), $this->expression($form->node(2))),
            'expr: expr OR expr' => new Binary(BinaryOperator::Or, $this->expression($form->node(0)), $this->expression($form->node(2))),
            'expr: expr LT|GT|GE|LE expr', 'expr: expr EQ|NE expr', 'expr: expr PLUS|MINUS expr', 'expr: expr STAR|SLASH|REM expr' => new Binary(
                BinaryOperator::from($form->token(1)->text),
                $this->expression($form->node(0)),
                $this->expression($form->node(2)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a literal term.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function term(Node $term): Scalar
    {
        $form = $this->lowering->productions->form($term);

        return match ($form->signature) {
            'term: INTEGER' => $this->lowering->leaves->record(new IntegerLiteral($form->token(0)->text)),
            default => throw ImplementationGap::production($form),
        };
    }
}
