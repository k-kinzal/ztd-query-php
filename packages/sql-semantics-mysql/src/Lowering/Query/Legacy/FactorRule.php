<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ExpressionRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\ItemRule;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;

/**
 * Lowers the table factors of the 5.x grammars, where one rule writes both derived tables and nested joins.
 *
 * Rule: MYSQL-FACTOR-LEGACY-001. Scope: table_factor (5.x),
 * select_derived_union, select_derived, select_derived_init. Parentheses
 * whose content is a single SELECT, with its union, ORDER BY and LIMIT, are
 * a derived table under the alias after them; without alias, directly
 * inside further parentheses that carry one, they are a parenthesized query
 * of that derived table. Parentheses around other references are a nested
 * join, which takes no alias, union, ORDER BY or LIMIT; a SELECT anywhere
 * else among table references is not inside parentheses. The server
 * rejects those forms with ER_SYNTAX_ERROR while parsing, before any name
 * is resolved: the actions of `table_factor`, `select_derived_union` and
 * `select_derived_init` in sql_yacc.yy of 5.6 and the `contextualize()` of
 * `PT_table_factor_select_sym`, `PT_table_factor_parenthesis` and
 * `PT_select_derived_union_*` in sql/parse_tree_nodes of 5.7. Constructs:
 * DerivedTable, NestedRelation, TableList, ParenthesizedQuery. Terminates:
 * the union is walked in a loop; recursion follows nested parentheses.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/derived-tables.html,
 * https://dev.mysql.com/doc/refman/5.7/en/join.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class FactorRule
{
    /**
     * The union productions of a 5.x derived table: whether they carry an ORDER BY or LIMIT after the operand.
     */
    private const UNIONS = [
        'select_derived_union: select_derived_union UNION_SYM union_option query_specification opt_union_order_or_limit' => true,
        'select_derived_union: select_derived_union UNION_SYM union_option query_specification' => false,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a 5.x table factor.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When a SELECT is not the first reference inside parentheses ("we are not in parentheses": ER_SYNTAX_ERROR of the 5.6 `select_derived_init` action and of 5.7 `PT_table_factor_select_sym::contextualize`)
     */
    public function factor(Form $form): Relation
    {
        if ($form->signature === 'table_factor: table_ident opt_use_partition opt_table_alias opt_key_definition') {
            return (new TableRule($this->lowering))->table($form->node);
        }
        if ($form->signature === 'table_factor: select_derived_init get_select_lex select_derived2' || $form->signature === 'table_factor: SELECT_SYM select_options select_item_list table_expression') {
            throw new AnalysisException('Syntax error: a SELECT among table references is written in parentheses as a derived table.');
        }
        $result = $this->parens($form);

        return $result instanceof Query ? new DerivedTable($result) : $result;
    }

    /**
     * Lowers a parenthesized 5.x table factor: a query when it is a derived table without alias, else the relation.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When a nested join has an alias, a union, an ORDER BY or a LIMIT (ER_SYNTAX_ERROR of the 5.6 `table_factor` and `select_derived_union` actions and of 5.7 `PT_table_factor_parenthesis` and `PT_select_derived_union_*::contextualize`)
     */
    public function parens(Form $form): Query|Relation
    {
        if ($form->signature === 'table_factor: ( get_select_lex select_derived_union ) opt_table_alias') {
            $this->lowering->options->skip($form->node(1));
            [$union, $alias] = [$form->node(2), $form->node(4)];
        } elseif ($form->signature === 'table_factor: ( select_derived_union ) opt_table_alias') {
            [$union, $alias] = [$form->node(1), $form->node(3)];
        } else {
            throw ImplementationGap::production($form);
        }
        $name = (new TableRule($this->lowering))->alias($alias);
        $steps = [];
        $step = $this->lowering->form($union);
        while (isset(self::UNIONS[$step->signature])) {
            $steps[] = [(new ExpressionRule($this->lowering))->quantifier($step->node(2)), (new SubqueryRule($this->lowering))->operand($step->node(3), self::UNIONS[$step->signature] ? $step->node(4) : null)];
            $step = $this->lowering->form($step->node(0));
        }
        if ($step->signature !== 'select_derived_union: select_derived opt_union_order_or_limit') {
            throw ImplementationGap::production($step);
        }
        $trailer = (new UnionRule($this->lowering))->ordering($step->node(1));
        $content = $this->content($step->node(0));
        if (is_array($content)) {
            if ($steps !== [] || !$trailer->empty() || $name !== null) {
                throw new AnalysisException('Syntax error: a nested join takes no alias, union, ORDER BY or LIMIT.');
            }

            return new NestedRelation(count($content) === 1 ? $content[0] : new TableList($content));
        }
        $operands = [[$content, $trailer]];
        $quantifiers = [];
        foreach (array_reverse($steps) as [$quantifier, $operand]) {
            $quantifiers[] = $quantifier;
            $operands[] = $operand;
        }
        $query = (new Chain($operands, $quantifiers, $this->lowering->profile->grammar))->query();

        return $name === null ? $query : new DerivedTable($query, $name, [], false, (new TableRule($this->lowering))->mark($alias));
    }

    /**
     * Lowers the content of 5.x parentheses: the query block of a derived table, the query of an inner derived table without alias, or the references of a nested join.
     *
     * @return Block|Query|list<Relation>
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When a nested join has an alias or a union
     */
    public function content(Node $derived): Block|Query|array
    {
        $form = $this->lowering->form($derived);
        if ($form->signature === 'select_derived: get_select_lex derived_table_list') {
            $this->lowering->options->skip($form->node(0));
            $form = $this->lowering->form($form->node(1));
        } elseif ($form->signature === 'select_derived: derived_table_list') {
            $form = $this->lowering->form($form->node(0));
        } else {
            throw ImplementationGap::production($form);
        }
        $factor = $this->single($form->node);
        if ($factor === null) {
            return (new JoinRule($this->lowering))->list($form->node);
        }
        if ($factor->signature === 'table_factor: SELECT_SYM select_options select_item_list table_expression') {
            $items = new ItemRule($this->lowering);

            return (new BlockRule($this->lowering))->expression($factor->node(3), $items->options($factor->node(1)), $items->items($factor->node(2)));
        }
        if ($factor->signature === 'table_factor: select_derived_init get_select_lex select_derived2') {
            $init = $this->lowering->form($factor->node(0));
            if ($init->signature !== 'select_derived_init: SELECT_SYM') {
                throw ImplementationGap::production($init);
            }
            $this->lowering->options->skip($factor->node(1));

            return (new BlockRule($this->lowering))->derived($factor->node(2));
        }
        $inner = $this->parens($factor);

        return $inner instanceof Query ? new ParenthesizedQuery($inner) : [$inner];
    }

    /**
     * Answers the table factor a reference list consists of when it is a single parenthesized or SELECT factor, else null.
     */
    public function single(Node $list): ?Form
    {
        $form = $this->lowering->form($list);
        if ($form->signature !== 'derived_table_list: esc_table_ref') {
            return null;
        }
        $escaped = $this->lowering->form($form->node(0));
        if ($escaped->signature !== 'esc_table_ref: table_ref') {
            return null;
        }
        $reference = $this->lowering->form($escaped->node(0));
        if ($reference->signature !== 'table_ref: table_factor') {
            return null;
        }
        $factor = $this->lowering->form($reference->node(0));

        return $factor->signature === 'table_factor: table_ident opt_use_partition opt_table_alias opt_key_definition' ? null : $factor;
    }
}
