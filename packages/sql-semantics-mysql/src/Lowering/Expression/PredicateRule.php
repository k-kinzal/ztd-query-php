<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the predicate level: IN, BETWEEN, LIKE, REGEXP, SOUNDS LIKE and MEMBER OF.
 *
 * Rule: MYSQL-PREDICATE-001. Scope: predicate, opt_escape (5.6, 5.7),
 * opt_of (8.0 and later). Every form takes a bit_expr operand; NOT before
 * the keyword is the negated form; `IN (expr)` keeps its single element;
 * the optional OF of MEMBER OF is not part of the structure; RLIKE is the
 * keyword REGEXP. Constructs: InList, InQuery, Between, Like, Regexp,
 * SoundsLike, MemberOf. Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html,
 * https://dev.mysql.com/doc/refman/8.4/en/string-comparison-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PredicateRule
{
    /**
     * The negated productions, by the position of their NOT.
     */
    private const NEGATED = [
        'predicate: bit_expr not IN_SYM ( subselect )' => 1, 'predicate: bit_expr not IN_SYM ( expr )' => 1,
        'predicate: bit_expr not IN_SYM ( expr , expr_list )' => 1, 'predicate: bit_expr not BETWEEN_SYM bit_expr AND_SYM predicate' => 1,
        'predicate: bit_expr not LIKE simple_expr opt_escape' => 1, 'predicate: bit_expr not REGEXP bit_expr' => 1,
        'predicate: bit_expr not IN_SYM table_subquery' => 1, 'predicate: bit_expr not LIKE simple_expr' => 1,
        'predicate: bit_expr not LIKE simple_expr ESCAPE_SYM simple_expr' => 1,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of predicate.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function predicate(Node $predicate): Scalar
    {
        $form = $this->lowering->form($predicate);
        $bits = new BitRule($this->lowering);
        if ($form->signature === 'predicate: bit_expr') {
            return $bits->bitExpression($form->node(0));
        }
        $offset = 0;
        if (isset(self::NEGATED[$form->signature])) {
            $this->lowering->options->skip($form->node(1));
            $offset = 1;
        }

        return $this->operation($form, $bits->bitExpression($form->node(0)), $offset);
    }

    /**
     * Lowers the operation of a predicate production over its lowered operand; offset is one when NOT is written.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function operation(Form $form, Scalar $operand, int $offset): Scalar
    {
        $negated = $offset === 1;
        $expressions = $this->lowering->expressions;

        return match (str_replace(' not ', ' ', $form->signature)) {
            'predicate: bit_expr IN_SYM ( subselect )' => new InQuery($operand, $this->lowering->queries->query($form->node(3 + $offset)), $negated),
            'predicate: bit_expr IN_SYM table_subquery' => new InQuery($operand, $this->lowering->queries->query($form->node(2 + $offset)), $negated),
            'predicate: bit_expr IN_SYM ( expr )' => new InList($operand, [$expressions->expression($form->node(3 + $offset))], $negated),
            'predicate: bit_expr IN_SYM ( expr , expr_list )' => new InList($operand, [$expressions->expression($form->node(3 + $offset)), ...$expressions->expressions($form->node(5 + $offset))], $negated),
            'predicate: bit_expr BETWEEN_SYM bit_expr AND_SYM predicate' => new Between($operand, $expressions->bitExpression($form->node(2 + $offset)), $this->predicate($form->node(4 + $offset)), $negated),
            'predicate: bit_expr LIKE simple_expr opt_escape' => new Like($operand, $expressions->simpleExpression($form->node(2 + $offset)), $this->escape($form->node(3 + $offset)), $negated),
            'predicate: bit_expr LIKE simple_expr' => new Like($operand, $expressions->simpleExpression($form->node(2 + $offset)), null, $negated),
            'predicate: bit_expr LIKE simple_expr ESCAPE_SYM simple_expr' => new Like($operand, $expressions->simpleExpression($form->node(2 + $offset)), $expressions->simpleExpression($form->node(4 + $offset)), $negated),
            'predicate: bit_expr REGEXP bit_expr' => new Regexp($operand, $expressions->bitExpression($form->node(2 + $offset)), $negated),
            'predicate: bit_expr SOUNDS_SYM LIKE bit_expr' => new SoundsLike($operand, $expressions->bitExpression($form->node(3))),
            'predicate: bit_expr MEMBER_SYM opt_of ( simple_expr )' => $this->member($form, $operand),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers MEMBER OF, confirming the optional OF.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function member(Form $form, Scalar $operand): MemberOf
    {
        $of = $this->lowering->form($form->node(2));
        if ($of->signature !== 'opt_of: OF_SYM' && $of->signature !== 'opt_of:') {
            throw ImplementationGap::production($of);
        }

        return new MemberOf($operand, $this->lowering->expressions->simpleExpression($form->node(4)), $of->signature === 'opt_of: OF_SYM' ? OptionalWords::Written : OptionalWords::Omitted);
    }

    /**
     * Lowers the optional ESCAPE clause of MySQL 5.6 and 5.7.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function escape(Node $escape): ?Scalar
    {
        $form = $this->lowering->form($escape);

        return match ($form->signature) {
            'opt_escape:' => null,
            'opt_escape: ESCAPE_SYM simple_expr' => $this->lowering->expressions->simpleExpression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
