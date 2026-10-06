<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\DatabaseRewrite;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\FilterKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Filter\ReplicationFilter;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers the filters of CHANGE REPLICATION FILTER.
 *
 * Rule: MYSQL-FILTER-001. Scope: filter_defs, filter_def, opt_filter_db_list,
 * filter_db_list, filter_db_ident, opt_filter_db_pair_list,
 * filter_db_pair_list, opt_filter_table_list, filter_table_list,
 * filter_table_ident, opt_filter_string_list, filter_string_list,
 * filter_string, filter_wild_db_table_string. Each filter production names one
 * OPT_REPLICATE_* option (FilterKind); its values are database names,
 * database-qualified table names, `db.table` pattern strings or pairs of
 * database names; `()` is the empty list, which clears the filter.
 * Constructs: ReplicationFilter, DatabaseRewrite. Terminates: the lists are
 * flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-filter.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class FilterRule
{
    /**
     * The filter productions, by the filter they set.
     */
    private const FILTERS = [
        'filter_def: REPLICATE_DO_DB EQ opt_filter_db_list' => FilterKind::DoDb,
        'filter_def: REPLICATE_IGNORE_DB EQ opt_filter_db_list' => FilterKind::IgnoreDb,
        'filter_def: REPLICATE_DO_TABLE EQ opt_filter_table_list' => FilterKind::DoTable,
        'filter_def: REPLICATE_IGNORE_TABLE EQ opt_filter_table_list' => FilterKind::IgnoreTable,
        'filter_def: REPLICATE_WILD_DO_TABLE EQ opt_filter_string_list' => FilterKind::WildDoTable,
        'filter_def: REPLICATE_WILD_IGNORE_TABLE EQ opt_filter_string_list' => FilterKind::WildIgnoreTable,
        'filter_def: REPLICATE_REWRITE_DB EQ opt_filter_db_pair_list' => FilterKind::RewriteDb,
    ];

    /**
     * The productions of the parenthesized value lists, by the spine productions of the list they hold.
     */
    private const LISTS = [
        'opt_filter_db_list: ( filter_db_list )' => ['filter_db_list: filter_db_ident', 'filter_db_list: filter_db_list , filter_db_ident'],
        'opt_filter_table_list: ( filter_table_list )' => ['filter_table_list: filter_table_ident', 'filter_table_list: filter_table_list , filter_table_ident'],
        'opt_filter_string_list: ( filter_string_list )' => ['filter_string_list: filter_string', 'filter_string_list: filter_string_list , filter_string'],
        'opt_filter_db_pair_list: ( filter_db_pair_list )' => [
            'filter_db_pair_list: ( filter_db_ident , filter_db_ident )', 'filter_db_pair_list: filter_db_pair_list , ( filter_db_ident , filter_db_ident )',
        ],
    ];

    /**
     * The productions of the empty value lists.
     */
    private const EMPTY = ['opt_filter_db_list: ( )' => true, 'opt_filter_table_list: ( )' => true, 'opt_filter_string_list: ( )' => true, 'opt_filter_db_pair_list: ( )' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the filter list.
     *
     * @return list<ReplicationFilter>
     * @throws ImplementationGap When a production has no rule
     */
    public function filters(Node $list): array
    {
        $filters = [];
        foreach ((new Spine($this->lowering))->items($list, ['filter_defs: filter_def', 'filter_defs: filter_defs , filter_def']) as $item) {
            $form = $this->lowering->form($item);
            $kind = self::FILTERS[$form->signature] ?? throw ImplementationGap::production($form);
            $filters[] = new ReplicationFilter($kind, $this->values($form->node(2)));
        }

        return $filters;
    }

    /**
     * Lowers a parenthesized value list.
     *
     * @return list<Name|QualifiedName|Text|DatabaseRewrite>
     * @throws ImplementationGap When a production has no rule
     */
    public function values(Node $values): array
    {
        $form = $this->lowering->form($values);
        if (isset(self::EMPTY[$form->signature])) {
            return [];
        }
        $spine = self::LISTS[$form->signature] ?? throw ImplementationGap::production($form);
        $items = (new Spine($this->lowering))->items($form->node(1), $spine);
        if ($form->signature === 'opt_filter_db_pair_list: ( filter_db_pair_list )') {
            $pairs = [];
            foreach (array_chunk($items, 2) as [$from, $to]) {
                $pairs[] = new DatabaseRewrite($this->database($from), $this->database($to));
            }

            return $pairs;
        }
        $lowered = [];
        foreach ($items as $item) {
            $lowered[] = $this->value($item);
        }

        return $lowered;
    }

    /**
     * Lowers one database name, table name or pattern of a filter.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Node $value): Name|QualifiedName|Text
    {
        $form = $this->lowering->form($value);

        return match ($form->signature) {
            'filter_db_ident: ident' => $this->database($value),
            'filter_table_ident: ident . ident', 'filter_table_ident: schema . ident' => new QualifiedName(
                $this->lowering->names->identifier($form->node(2)),
                $this->lowering->names->identifier($form->node(0)),
            ),
            'filter_string: filter_wild_db_table_string' => $this->pattern($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a database name.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function database(Node $database): Name
    {
        $form = $this->lowering->form($database);
        if ($form->signature !== 'filter_db_ident: ident') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->names->identifier($form->node(0));
    }

    /**
     * Lowers a `db.table` pattern string.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function pattern(Node $pattern): Text
    {
        $form = $this->lowering->form($pattern);
        if ($form->signature !== 'filter_wild_db_table_string: TEXT_STRING_sys_nonewline') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->literals->text($form->node(0));
    }
}
