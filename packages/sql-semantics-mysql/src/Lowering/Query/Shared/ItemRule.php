<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Query\ItemNaming;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the select options, the select list and select list aliases of every grammar generation.
 *
 * Rule: MYSQL-SELECT-ITEMS-001. Scope: select_options, select_option_list,
 * select_option, query_spec_option, opt_query_spec_options,
 * query_spec_option_list, query_expression_option,
 * opt_query_expression_options, query_expression_option_list,
 * select_item_list, select_item, select_alias. Options and items keep their
 * written order; a select alias written as a string names the item like an
 * identifier. An item without alias that the server names after its text
 * keeps the layout of its expression (MYSQL-ITEM-LAYOUT-001) unless the
 * expression is written exactly as its canonical rendering, which an item
 * without layout stands for; an item that names itself, such as a column
 * reference or a string (MYSQL-SELECT-ITEM-NAME-001), keeps none. Constructs: SelectOption, SelectExpression, Star, and the
 * TableWildcard of the leaf rules. Terminates: lists are flattened
 * iteratively. Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class ItemRule
{
    /**
     * The productions of one select option.
     */
    private const OPTIONS = [
        'select_option: SQL_NO_CACHE_SYM' => SelectOption::NoCache, 'select_option: SQL_CACHE_SYM' => SelectOption::Cache,
        'query_spec_option: STRAIGHT_JOIN' => SelectOption::StraightJoin, 'query_spec_option: HIGH_PRIORITY' => SelectOption::HighPriority,
        'query_spec_option: DISTINCT' => SelectOption::Distinct, 'query_spec_option: SQL_SMALL_RESULT' => SelectOption::SmallResult,
        'query_spec_option: SQL_BIG_RESULT' => SelectOption::BigResult, 'query_spec_option: SQL_BUFFER_RESULT' => SelectOption::BufferResult,
        'query_spec_option: SQL_CALC_FOUND_ROWS' => SelectOption::CalcFoundRows, 'query_spec_option: ALL' => SelectOption::All,
        'query_expression_option: STRAIGHT_JOIN' => SelectOption::StraightJoin, 'query_expression_option: HIGH_PRIORITY' => SelectOption::HighPriority,
        'query_expression_option: DISTINCT' => SelectOption::Distinct, 'query_expression_option: SQL_SMALL_RESULT' => SelectOption::SmallResult,
        'query_expression_option: SQL_BIG_RESULT' => SelectOption::BigResult, 'query_expression_option: SQL_BUFFER_RESULT' => SelectOption::BufferResult,
        'query_expression_option: SQL_CALC_FOUND_ROWS' => SelectOption::CalcFoundRows, 'query_expression_option: ALL' => SelectOption::All,
    ];

    /**
     * The productions that hold an option list at their first position, or write none.
     */
    private const LISTS = [
        'select_options:' => false, 'opt_query_spec_options:' => false, 'opt_query_expression_options:' => false,
        'select_options: select_option_list' => true, 'opt_query_spec_options: query_spec_option_list' => true,
        'opt_query_expression_options: query_expression_option_list' => true,
    ];

    /**
     * The spine productions of the option lists.
     */
    private const SPINES = [
        'select_option_list: select_option_list select_option' => true, 'select_option_list: select_option' => true,
        'query_spec_option_list: query_spec_option_list query_spec_option' => true, 'query_spec_option_list: query_spec_option' => true,
        'query_expression_option_list: query_expression_option_list query_expression_option' => true, 'query_expression_option_list: query_expression_option' => true,
    ];

    /**
     * The productions of a select alias by the position of the name; null when absent.
     */
    private const ALIASES = [
        'select_alias:' => null, 'select_alias: AS ident' => 1, 'select_alias: AS TEXT_STRING_sys' => 1, 'select_alias: AS TEXT_STRING_validated' => 1,
        'select_alias: ident' => 0, 'select_alias: TEXT_STRING_sys' => 0, 'select_alias: TEXT_STRING_validated' => 0,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the select options in written order.
     *
     * @return list<SelectOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $options): array
    {
        $form = $this->lowering->form($options);
        $list = self::LISTS[$form->signature] ?? throw ImplementationGap::production($form);
        if (!$list) {
            return [];
        }
        $spine = $this->lowering->form($form->node(0));
        if (!isset(self::SPINES[$spine->signature])) {
            throw ImplementationGap::production($spine);
        }
        $result = [];
        foreach ((new Lists())->items($spine->node) as $item) {
            $option = $this->lowering->form($item);
            if ($option->signature === 'select_option: query_expression_option' || $option->signature === 'select_option: query_spec_option') {
                $option = $this->lowering->form($option->node(0));
            }
            $result[] = self::OPTIONS[$option->signature] ?? throw ImplementationGap::production($option);
        }

        return $result;
    }

    /**
     * Lowers the select list in written order.
     *
     * @return list<SelectExpression|Star|TableWildcard>
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $list): array
    {
        $form = $this->lowering->form($list);
        if (!in_array($form->signature, ['select_item_list: select_item_list , select_item', 'select_item_list: select_item', 'select_item_list: *'], true)) {
            throw ImplementationGap::production($form);
        }
        $items = [];
        foreach ((new Lists())->elements($list) as $element) {
            if ($element instanceof Token) {
                if ($element->name === '*') {
                    $items[] = new Star();
                }
                continue;
            }
            $items[] = $this->item($element);
        }

        return $items;
    }

    /**
     * Lowers one select list item.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function item(Node $item): SelectExpression|TableWildcard
    {
        $form = $this->lowering->form($item);
        if ($form->signature === 'select_item: remember_name table_wild remember_end' || $form->signature === 'select_item: remember_name expr remember_end select_alias') {
            $this->lowering->options->skip($form->node(0));
            $this->lowering->options->skip($form->node(2));
        }

        return match ($form->signature) {
            'select_item: table_wild' => $this->lowering->names->wildcard($form->node(0)),
            'select_item: remember_name table_wild remember_end' => $this->lowering->names->wildcard($form->node(1)),
            'select_item: expr select_alias' => $this->expression($form->node(0), $form->node(1)),
            'select_item: remember_name expr remember_end select_alias' => $this->expression($form->node(1), $form->node(3)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a projected expression with its alias, keeping the layout of an expression without alias that the server names after its text and that is not written as the canonical rendering (MYSQL-ITEM-LAYOUT-001).
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function expression(Node $expression, Node $alias): SelectExpression
    {
        $name = $this->alias($alias);
        $lowered = $this->lowering->expressions->expression($expression);
        $naming = new ItemNaming($this->lowering->profile);
        $layout = $name === null && $naming->own($lowered) === null ? (new ItemLayout($this->lowering->profile->grammar))->of($expression) : null;
        if ($layout !== null && $layout->text() === $naming->canonical($lowered)) {
            $layout = null;
        }

        return new SelectExpression($lowered, $name, $layout, $this->mark($alias));
    }

    /**
     * Answers whether a select alias is written after AS; no alias answers AS, the mark of an item without alias.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function mark(Node $alias): AliasMark
    {
        $form = $this->lowering->form($alias);
        if (!array_key_exists($form->signature, self::ALIASES)) {
            throw ImplementationGap::production($form);
        }

        return self::ALIASES[$form->signature] === 0 ? AliasMark::Bare : AliasMark::As;
    }

    /**
     * Lowers a select alias; no alias is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alias(Node $alias): ?Name
    {
        $form = $this->lowering->form($alias);
        if (!array_key_exists($form->signature, self::ALIASES)) {
            throw ImplementationGap::production($form);
        }
        $position = self::ALIASES[$form->signature];

        return $position === null ? null : $this->lowering->names->identifier($form->node($position));
    }
}
