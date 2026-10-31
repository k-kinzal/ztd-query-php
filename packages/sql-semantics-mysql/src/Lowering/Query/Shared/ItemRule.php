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
 * without layout stands for. Unqualified references also retain observable
 * spelling because they may resolve to stored-program variables. Other
 * self-naming items, such as qualified columns and strings, keep none.
 * Constructs: SelectOption, SelectExpression, Star, and the
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
     * @param bool $first Whether the options belong to the first query block of the statement; a derived table, a subquery or a later operand of a 5.x union is never that block
     * @return list<SelectOption>
     * @throws ImplementationGap When a production has no rule
     * @throws \SqlSemantics\Construction\RejectedInput When MySQL 5.6 or 5.7 refuses a modifier of the block while parsing it
     */
    public function options(Node $options, bool $first = true): array
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
        $nodes = [];
        foreach ((new Lists())->items($spine->node) as $item) {
            $option = $this->lowering->form($item);
            if ($option->signature === 'select_option: query_expression_option' || $option->signature === 'select_option: query_spec_option') {
                $option = $this->lowering->form($option->node(0));
            }
            $result[] = self::OPTIONS[$option->signature] ?? throw ImplementationGap::production($option);
            $nodes[] = $item;
        }
        $this->placed($result, $nodes, $first);

        return $result;
    }

    /**
     * Refuses the modifiers of a query block where MySQL 5.6 and 5.7 refuse them while parsing the block.
     *
     * MySQL 5.6 refuses the first of HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS, SQL_CACHE
     * and SQL_NO_CACHE in a block that is not the first of the statement (ER_CANT_USE_OPTION_HERE),
     * before a conflict of its query cache modifiers; both releases then refuse a query cache
     * modifier that meets an earlier one (ER_DUP_ARGUMENT, ER_WRONG_USAGE), at the second modifier
     * (verified on live 5.6.51 and 5.7.44 servers). Later releases check the modifiers of the other
     * blocks once the statement is read.
     *
     * @param list<SelectOption> $options The lowered modifiers in written order
     * @param list<Node> $nodes The nodes of the modifiers
     * @param bool $first Whether the block is the first query block of its statement
     * @throws \SqlSemantics\Construction\RejectedInput When the release refuses a modifier of the block
     */
    public function placed(array $options, array $nodes, bool $first): void
    {
        $grammar = $this->lowering->profile->grammar;
        $rules = new \SqlSemantics\Platform\MySql\Rules\Query\SelectOptions();
        $index = $grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651 && !$first ? $rules->placement($options) : null;
        if ($index !== null) {
            throw new \SqlSemantics\Construction\RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Option\RestrictedSelectOption($options[$index]), (new \SqlSemantics\Construction\SourceRanges())->span($nodes[$index])[1] ?? 0);
        }
        $conflict = $rules->conflict($options, $grammar);
        if ($conflict !== null) {
            throw new \SqlSemantics\Construction\RejectedInput($conflict[1], (new \SqlSemantics\Construction\SourceRanges())->span($nodes[$conflict[0]])[1] ?? 0);
        }
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
        return $this->resolved($this->lowering->expressions->expression($expression), $expression, $alias);
    }

    /**
     * Names an already lowered scalar projection without creating another operand occurrence.
     */
    public function resolved(\SqlSemantics\Statement\Scalar $lowered, Node $expression, Node $alias): SelectExpression
    {
        $name = $this->alias($alias);
        $naming = new ItemNaming($this->lowering->profile);
        $layout = $name === null && $naming->keeps($lowered) ? (new ItemLayout($this->lowering->profile->grammar))->of($expression, $naming->own($lowered) instanceof \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse) : null;
        $layout = $layout === null ? null : (new ItemLayout($this->lowering->profile->grammar))->names($layout, $lowered, $this->lowering->profile, $this->lowering->inputClient);
        if ($layout !== null && $layout->tokens[0]->gap === '' && $layout->text() === $naming->canonical($lowered)) {
            $layout = null;
        }

        return new SelectExpression($lowered, $name, $layout, $this->mark($alias), $layout === null ? null : $this->lowering->profile);
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
