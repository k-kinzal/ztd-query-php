<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOperator;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\NestedInput;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableCall;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

/**
 * Lowers FROM clauses into input relations.
 *
 * Rule: SQLITE-FROM-LOWER-001. Scope: from, seltablist, stl_prefix, joinop,
 * on_using, indexed_opt, indexed_by. One term without a constraint is that
 * term; anything else is a chain of the terms in written order, each with
 * the operator before it and the constraint after it. A word before JOIN is
 * a join keyword when it is written as one and a name otherwise.
 * Terminates: the term list is walked along its spine in a loop; recursion
 * follows parentheses. Source: https://sqlite.org/lang_select.html#the_from_clause.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class FromRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a `from`: the input relation, or null when the clause is absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function from(Node $from): ?Relation
    {
        $form = $this->lowering->productions->form($from);

        return match ($form->signature) {
            'from:' => null,
            'from: FROM seltablist' => $this->terms($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `seltablist`: one term, or the chain of its terms.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function terms(Node $list): Relation
    {
        $productions = $this->lowering->productions;
        $forms = [];
        $operators = [];
        for ($node = $list; $node !== null;) {
            $form = $productions->form($node);
            $forms[] = $form;
            $prefix = $productions->form($form->node(0));
            [$node, $operators[]] = match ($prefix->signature) {
                'stl_prefix:' => [null, null],
                'stl_prefix: seltablist joinop' => [$prefix->node(0), $prefix->node(1)],
                default => throw ImplementationGap::production($prefix),
            };
        }
        $first = null;
        $leading = null;
        $steps = [];
        foreach (array_reverse($forms, true) as $index => $form) {
            $operator = $operators[$index] === null ? null : $this->operator($operators[$index]);
            $relation = $this->term($form);
            $constraint = $this->constraint($form->node(count($form->node->children) - 1));
            if ($operator === null) {
                [$first, $leading] = [$relation, $constraint];
            } else {
                $steps[] = new JoinStep($operator, $relation, $constraint);
            }
        }
        if ($first === null) {
            throw ImplementationGap::rule('seltablist without a first term');
        }

        return $steps === [] && $leading === null ? $first : new JoinChain($first, $steps, $leading);
    }

    /**
     * Lowers the relation of one term.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function term(Form $form): Relation
    {
        $names = $this->lowering->names;

        return match ($form->signature) {
            'seltablist: stl_prefix nm dbnm as on_using' => new TableInput($names->scoped($form->node(1), $form->node(2)), $this->lowering->results->alias($form->node(3)), null, $this->lowering->results->keyword($form->node(3))),
            'seltablist: stl_prefix nm dbnm as indexed_by on_using' => new TableInput($names->scoped($form->node(1), $form->node(2)), $this->lowering->results->alias($form->node(3)), $this->indexed($form->node(4)), $this->lowering->results->keyword($form->node(3))),
            'seltablist: stl_prefix nm dbnm LP exprlist RP as on_using' => new TableCall($names->scoped($form->node(1), $form->node(2)), $this->lowering->expressions->list($form->node(4)), $this->lowering->results->alias($form->node(6)), $this->lowering->results->keyword($form->node(6))),
            'seltablist: stl_prefix LP select RP as on_using' => new DerivedQuery($this->lowering->selects->select($form->node(2)), $this->lowering->results->alias($form->node(4)), $this->lowering->results->keyword($form->node(4))),
            'seltablist: stl_prefix LP seltablist RP as on_using' => new NestedInput($this->terms($form->node(2)), $this->lowering->results->alias($form->node(4)), $this->lowering->results->keyword($form->node(4))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `joinop`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function operator(Node $operator): JoinOperator
    {
        $form = $this->lowering->productions->form($operator);
        if ($form->signature === 'joinop: COMMA|JOIN') {
            return new JoinOperator($form->token(0)->name === 'COMMA');
        }
        $words = match ($form->signature) {
            'joinop: JOIN_KW JOIN' => [JoinKeyword::from(strtoupper($form->token(0)->text))],
            'joinop: JOIN_KW nm JOIN' => [JoinKeyword::from(strtoupper($form->token(0)->text)), $this->word($form->node(1))],
            'joinop: JOIN_KW nm nm JOIN' => [JoinKeyword::from(strtoupper($form->token(0)->text)), $this->word($form->node(1)), $this->word($form->node(2))],
            default => throw ImplementationGap::production($form),
        };

        return new JoinOperator(false, $words);
    }

    /**
     * Lowers a word written before JOIN: a join keyword when it is written as one, a name otherwise.
     */
    public function word(Node $name): JoinKeyword|Name
    {
        $token = $this->lowering->productions->form($name)->token(0);
        $keyword = $token->name === 'JOIN_KW' ? JoinKeyword::tryFrom(strtoupper($token->text)) : null;

        return $keyword ?? $this->lowering->names->name($name);
    }

    /**
     * Lowers an `on_using`: the constraint, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function constraint(Node $constraint): JoinOn|JoinUsing|null
    {
        $form = $this->lowering->productions->form($constraint);

        return match ($form->signature) {
            'on_using:' => null,
            'on_using: ON expr' => new JoinOn($this->lowering->expressions->expression($form->node(1))),
            'on_using: USING LP idlist RP' => new JoinUsing($this->lowering->names->list($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an `indexed_by`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function indexed(Node $clause): IndexChoice
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'indexed_by: INDEXED BY nm' => new IndexChoice($this->lowering->names->name($form->node(2))),
            'indexed_by: NOT INDEXED' => new IndexChoice(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an `indexed_opt`: the index choice, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalIndexed(Node $clause): ?IndexChoice
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'indexed_opt:' => null,
            'indexed_opt: indexed_by' => $this->indexed($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }
}
