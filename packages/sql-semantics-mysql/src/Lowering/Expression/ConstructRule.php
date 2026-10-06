<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextMode;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\AtTimeZone;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastAtLocal;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CharsetConversion;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the keyword constructs of simple_expr: CASE, CAST, CONVERT, BINARY and MATCH … AGAINST.
 *
 * Rule: MYSQL-CONSTRUCT-001. Scope: the simple_expr productions of these
 * constructs and opt_expr, when_list, opt_else, opt_array_cast,
 * opt_interval, ident_list_arg, ident_list, fulltext_options,
 * opt_natural_language_mode, opt_query_expansion. `CONVERT(expr, type)` is
 * a CAST; the parentheses around the MATCH columns and the words
 * `IN NATURAL LANGUAGE MODE` are not part of the structure. The WHEN
 * branches are flattened along their list spine. Constructs:
 * CaseExpression, CaseBranch, Cast, CastAtLocal, AtTimeZone,
 * CharsetConversion, BinaryCast, FullTextSearch. Terminates: list spines
 * are flattened in a loop; every other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fulltext-search.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ConstructRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one of the constructs.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function lower(Form $form): Scalar
    {
        $expressions = $this->lowering->expressions;
        $types = $this->lowering->types;

        return match ($form->signature) {
            'simple_expr: CASE_SYM opt_expr when_list opt_else END' => new CaseExpression($this->optional($form->node(1)), $this->branches($form->node(2)), $this->optional($form->node(3))),
            'simple_expr: CAST_SYM ( expr AS cast_type )', 'simple_expr: CONVERT_SYM ( expr , cast_type )' => new Cast($expressions->expression($form->node(2)), $types->castTarget($form->node(4))),
            'simple_expr: CAST_SYM ( expr AS cast_type opt_array_cast )' => new Cast($expressions->expression($form->node(2)), $types->castTarget($form->node(4)), $this->flag($form->node(5))),
            'simple_expr: CAST_SYM ( expr AT_SYM LOCAL_SYM AS cast_type opt_array_cast )' => new CastAtLocal($expressions->expression($form->node(2)), $types->castTarget($form->node(6)), $this->flag($form->node(7))),
            'simple_expr: CAST_SYM ( expr AT_SYM TIME_SYM ZONE_SYM opt_interval TEXT_STRING_literal AS DATETIME_SYM type_datetime_precision )' => $this->zone($form),
            'simple_expr: CONVERT_SYM ( expr USING charset_name )' => new CharsetConversion($expressions->expression($form->node(2)), $this->lowering->charsets->charset($form->node(4))),
            'simple_expr: BINARY simple_expr', 'simple_expr: BINARY_SYM simple_expr' => new BinaryCast($expressions->simpleExpression($form->node(1))),
            'simple_expr: MATCH ident_list_arg AGAINST ( bit_expr fulltext_options )' => $this->search($form),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CAST … AT TIME ZONE.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function zone(Form $form): AtTimeZone
    {
        return new AtTimeZone($this->lowering->expressions->expression($form->node(2)), $this->lowering->literals->text($form->node(7)), $this->flag($form->node(6)), $this->lowering->types->precision($form->node(10)));
    }

    /**
     * Lowers the optional operand of CASE or its ELSE result: a node of opt_expr or opt_else.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function optional(Node $optional): ?Scalar
    {
        $form = $this->lowering->form($optional);

        return match ($form->signature) {
            'opt_expr:', 'opt_else:' => null,
            'opt_expr: expr' => $this->lowering->expressions->expression($form->node(0)),
            'opt_else: ELSE expr' => $this->lowering->expressions->expression($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the WHEN branches of a CASE expression.
     *
     * @return list<CaseBranch>
     * @throws ImplementationGap When a production has no rule
     */
    public function branches(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature !== 'when_list: WHEN_SYM expr THEN_SYM expr' && $form->signature !== 'when_list: when_list WHEN_SYM expr THEN_SYM expr') {
            throw ImplementationGap::production($form);
        }
        $items = (new Lists())->items($list);
        $branches = [];
        for ($index = 0; $index + 1 < count($items); $index += 2) {
            $branches[] = new CaseBranch($this->lowering->expressions->expression($items[$index]), $this->lowering->expressions->expression($items[$index + 1]));
        }

        return $branches;
    }

    /**
     * Lowers the optional ARRAY of a cast or INTERVAL of a time zone: whether the keyword is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function flag(Node $option): bool
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'opt_array_cast:', 'opt_interval:' => false,
            'opt_array_cast: ARRAY_SYM', 'opt_interval: INTERVAL_SYM' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers MATCH ... AGAINST, keeping whether the column list is parenthesized and whether IN NATURAL LANGUAGE MODE is written.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function search(Form $form): FullTextSearch
    {
        $options = $this->lowering->form($form->node(5));
        $stated = $options->signature === 'fulltext_options: opt_natural_language_mode opt_query_expansion'
            && $this->lowering->form($options->node(0))->signature === 'opt_natural_language_mode: IN_SYM NATURAL LANGUAGE_SYM MODE_SYM';
        $parenthesized = $this->lowering->form($form->node(1))->signature === 'ident_list_arg: ( ident_list )';

        return new FullTextSearch(
            $this->columns($form->node(1)),
            $this->lowering->expressions->bitExpression($form->node(4)),
            $this->mode($form->node(5)),
            $parenthesized ? OptionalWords::Written : OptionalWords::Omitted,
            $stated ? OptionalWords::Written : OptionalWords::Omitted,
        );
    }

    /**
     * Lowers the columns of MATCH: a node of ident_list_arg.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Name\ColumnUse>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $argument): array
    {
        $form = $this->lowering->form($argument);
        $list = match ($form->signature) {
            'ident_list_arg: ident_list' => $form->node(0),
            'ident_list_arg: ( ident_list )' => $form->node(1),
            default => throw ImplementationGap::production($form),
        };
        $first = $this->lowering->form($list);
        if ($first->signature !== 'ident_list: simple_ident' && $first->signature !== 'ident_list: ident_list , simple_ident') {
            throw ImplementationGap::production($first);
        }
        $columns = [];
        foreach ((new Lists())->items($list) as $item) {
            $columns[] = $this->lowering->names->column($item);
        }

        return $columns;
    }

    /**
     * Lowers the search mode of AGAINST: a node of fulltext_options.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function mode(Node $options): FullTextMode
    {
        $form = $this->lowering->form($options);
        if ($form->signature === 'fulltext_options: IN_SYM BOOLEAN_SYM MODE_SYM') {
            return FullTextMode::Boolean;
        }
        if ($form->signature !== 'fulltext_options: opt_natural_language_mode opt_query_expansion') {
            throw ImplementationGap::production($form);
        }
        $natural = $this->lowering->form($form->node(0));
        if ($natural->signature !== 'opt_natural_language_mode:' && $natural->signature !== 'opt_natural_language_mode: IN_SYM NATURAL LANGUAGE_SYM MODE_SYM') {
            throw ImplementationGap::production($natural);
        }

        return match ($this->lowering->form($form->node(1))->signature) {
            'opt_query_expansion:' => FullTextMode::NaturalLanguage,
            'opt_query_expansion: WITH QUERY_SYM EXPANSION_SYM' => FullTextMode::QueryExpansion,
            default => throw ImplementationGap::production($this->lowering->form($form->node(1))),
        };
    }
}
