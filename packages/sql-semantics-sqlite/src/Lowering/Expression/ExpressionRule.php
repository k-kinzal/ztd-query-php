<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Expression;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers expression productions into scalar structure.
 *
 * Rule: SQLITE-EXPR-001. Scope: expr (the forms that are no operator and no
 * function call), exprlist, nexprlist, paren_exprlist, where_opt,
 * case_operand, case_exprlist, case_else. Operands keep their written order
 * and every parenthesis pair is kept as a grouping, so the structure states
 * the evaluation order the parser chose. An unqualified word is a column
 * use, a double-quoted word or a truth word by how it is written
 * (SQLITE-DOUBLE-QUOTED-WORD-001, SQLITE-TRUTH-WORD-001). Terminates: every
 * operand is a strict subtree and lists are flattened iteratively.
 * Source: https://sqlite.org/lang_expr.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ExpressionRule
{
    private readonly OperatorRule $operators;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->operators = new OperatorRule($lowering);
    }

    /**
     * Lowers one expression.
     */
    public function expression(Node $expression): Scalar
    {
        $form = $this->lowering->productions->form($expression);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'expr: term' => $this->term($form->node(0)),
            'expr: LP expr RP' => new Grouped($this->expression($form->node(1))),
            'expr: idj' => $this->word($form->token(0)),
            'expr: nm DOT nm' => $this->column([$form->node(2), $form->node(0)]),
            'expr: nm DOT nm DOT nm' => $this->column([$form->node(4), $form->node(2), $form->node(0)]),
            'expr: VARIABLE' => $this->parameter($form->token(0)),
            'expr: expr COLLATE ids' => new Collate($this->expression($form->node(0)), $names->token($form->token(2))),
            'expr: CAST LP expr AS typetoken RP' => new Cast($this->expression($form->node(2)), $this->lowering->typeNames->named($form->node(4))),
            'expr: LP nexprlist COMMA expr RP' => new RowExpression([...$this->items($form->node(1)), $this->expression($form->node(3))]),
            'expr: LP select RP' => new ScalarSubquery($this->lowering->selects->select($form->node(1))),
            'expr: EXISTS LP select RP' => new Exists($this->lowering->selects->select($form->node(2))),
            'expr: CASE case_operand case_exprlist case_else END' => $this->conditional($form->node(1), $form->node(2), $form->node(3)),
            'expr: RAISE LP IGNORE RP' => new Raise(RaiseAction::Ignore),
            'expr: RAISE LP raisetype COMMA expr RP' => new Raise(RaiseAction::from($this->lowering->conflicts->raised($form->node(2))->value), $this->expression($form->node(4))),
            default => $this->operators->expression($form),
        };
    }

    /**
     * Lowers a literal term.
     */
    public function term(Node $term): Scalar
    {
        return $this->lowering->literals->term($term);
    }

    /**
     * Lowers an unqualified word by how it is written: in double quotes, as bare TRUE or FALSE, or as any other name.
     */
    public function word(Token $token): Scalar
    {
        if (str_starts_with($token->text, '"')) {
            return new DoubleQuotedWord($this->lowering->names->token($token));
        }
        $truth = ['true' => true, 'false' => false][strtolower($token->text)] ?? null;
        if ($truth !== null) {
            return $this->lowering->leaves->record(new TruthWord($truth));
        }

        return new ColumnUse($this->lowering->names->token($token));
    }

    /**
     * Lowers a qualified column use from its name nodes: the column, the table and the optional schema.
     *
     * @param array{0: Node, 1: Node, 2?: Node} $parts
     */
    public function column(array $parts): ColumnUse
    {
        $names = $this->lowering->names;
        $schema = isset($parts[2]) ? $names->name($parts[2]) : null;
        $table = $names->name($parts[1]);

        return new ColumnUse($names->name($parts[0]), new QualifiedName($table, $schema));
    }

    /**
     * Lowers a bind parameter token and records it as an operand leaf.
     */
    public function parameter(Token $token): BindParameter
    {
        return $this->lowering->leaves->record(new BindParameter(ParameterPrefix::from($token->text[0] ?? ''), substr($token->text, 1)));
    }

    /**
     * Lowers a CASE expression from its operand, branches and ELSE part.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function conditional(Node $operand, Node $branches, Node $otherwise): CaseExpression
    {
        $productions = $this->lowering->productions;
        $base = $productions->form($operand);
        $list = $productions->form($branches);
        $else = $productions->form($otherwise);
        if (!in_array($base->signature, ['case_operand: expr', 'case_operand:'], true) || !in_array($list->signature, ['case_exprlist: case_exprlist WHEN expr THEN expr', 'case_exprlist: WHEN expr THEN expr'], true) || !in_array($else->signature, ['case_else: ELSE expr', 'case_else:'], true)) {
            throw ImplementationGap::production($list);
        }
        $items = (new Lists())->items($branches);
        $cases = [];
        for ($index = 0; $index < count($items); $index += 2) {
            $cases[] = new CaseBranch($this->expression($items[$index]), $this->expression($items[$index + 1]));
        }

        return new CaseExpression(
            $base->signature === 'case_operand:' ? null : $this->expression($base->node(0)),
            $cases,
            $else->signature === 'case_else:' ? null : $this->expression($else->node(1)),
        );
    }

    /**
     * Lowers an `exprlist` into its expressions in written order.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When the production has no rule
     */
    public function list(Node $exprlist): array
    {
        $form = $this->lowering->productions->form($exprlist);

        return match ($form->signature) {
            'exprlist:' => [],
            'exprlist: nexprlist' => $this->items($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `nexprlist` into its expressions in written order.
     *
     * @return list<Scalar>
     * @throws ImplementationGap When the production has no rule
     */
    public function items(Node $nexprlist): array
    {
        $form = $this->lowering->productions->form($nexprlist);
        if ($form->signature !== 'nexprlist: nexprlist COMMA expr' && $form->signature !== 'nexprlist: expr') {
            throw ImplementationGap::production($form);
        }
        $items = [];
        foreach ((new Lists())->items($nexprlist) as $expression) {
            $items[] = $this->expression($expression);
        }

        return $items;
    }

    /**
     * Lowers a `paren_exprlist`: the expressions, or null when no parentheses are written.
     *
     * @return list<Scalar>|null
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalList(Node $list): ?array
    {
        $form = $this->lowering->productions->form($list);

        return match ($form->signature) {
            'paren_exprlist:' => null,
            'paren_exprlist: LP exprlist RP' => $this->list($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `where_opt`: the predicate, or null when the clause is absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function where(Node $where): ?Scalar
    {
        $form = $this->lowering->productions->form($where);

        return match ($form->signature) {
            'where_opt:' => null,
            'where_opt: WHERE expr' => $this->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
