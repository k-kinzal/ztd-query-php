<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Shared;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintScope;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\PrimaryIndex;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\SamplingMethod;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers the parts of a named table reference shared by every grammar generation.
 *
 * Rule: MYSQL-TABLE-PARTS-001. Scope: single_table and the 5.x
 * table_factor of a named table, opt_use_partition, use_partition,
 * using_list, opt_table_alias, table_alias, opt_key_definition,
 * opt_index_hints_list, index_hints_list, index_hint_definition,
 * index_hint_type, index_hint_clause, opt_key_usage_list, key_usage_list,
 * key_usage_element, opt_derived_column_list, simple_ident_list,
 * opt_tablesample_clause, sampling_method, sampling_percentage. KEY and
 * INDEX in an index hint are synonyms (the writer emits INDEX); PRIMARY
 * names the primary key. Constructs: TableReference, IndexHint,
 * PrimaryIndex, TableSample. Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/join.html,
 * https://dev.mysql.com/doc/refman/8.4/en/index-hints.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-selection.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Query
 */
final class TableRule
{
    /**
     * The named table productions of every generation.
     */
    private const TABLES = [
        'single_table: table_ident opt_use_partition opt_table_alias opt_key_definition' => true,
        'single_table: table_ident opt_use_partition opt_table_alias opt_key_definition opt_tablesample_clause' => true,
        'table_factor: table_ident opt_use_partition opt_table_alias opt_key_definition' => true,
    ];

    /**
     * The scopes of an index hint.
     */
    private const SCOPES = [
        'index_hint_clause:' => null, 'index_hint_clause: FOR_SYM JOIN_SYM' => IndexHintScope::Join,
        'index_hint_clause: FOR_SYM ORDER_SYM BY' => IndexHintScope::OrderBy, 'index_hint_clause: FOR_SYM GROUP_SYM BY' => IndexHintScope::GroupBy,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a named table with its partitions, alias, index hints and sampling clause.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function table(Node $table): TableReference
    {
        $form = $this->lowering->form($table);
        if (!isset(self::TABLES[$form->signature])) {
            throw ImplementationGap::production($form);
        }
        $hints = $this->lowering->form($form->node(3));
        if ($hints->signature !== 'opt_key_definition: opt_index_hints_list') {
            throw ImplementationGap::production($hints);
        }

        return new TableReference(
            $this->lowering->names->qualified($form->node(0)),
            $this->alias($form->node(2)),
            $this->partitions($form->node(1)),
            $this->hints($hints->node(0)),
            count($form->node->children) === 5 ? $this->sample($form->node(4)) : null,
            $this->mark($form->node(2)),
            $this->lowering->names->dotted($form->node(0)) ? OptionalWords::Written : OptionalWords::Omitted,
        );
    }

    /**
     * Lowers an explicit partition selection; an absent selection is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function partitions(Node $selection): array
    {
        $form = $this->lowering->form($selection);
        if ($form->signature === 'use_partition: PARTITION_SYM ( using_list ) have_partitioning') {
            $this->lowering->options->skip($form->node(4));
        }

        return match ($form->signature) {
            'opt_use_partition:' => [],
            'opt_use_partition: use_partition' => $this->partitions($form->node(0)),
            'use_partition: PARTITION_SYM ( using_list )', 'use_partition: PARTITION_SYM ( using_list ) have_partitioning' => $this->names($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a comma-separated list of identifiers: using_list or simple_ident_list.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function names(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'using_list: ident_string_list') {
            return $this->lowering->names->identifiers($form->node(0));
        }
        if (!in_array($form->signature, ['using_list: ident', 'using_list: using_list , ident', 'simple_ident_list: ident', 'simple_ident_list: simple_ident_list , ident'], true)) {
            throw ImplementationGap::production($form);
        }
        $names = [];
        foreach ((new Lists())->items($list) as $item) {
            $names[] = $this->lowering->names->identifier($item);
        }

        return $names;
    }

    /**
     * Lowers the column names of a derived table or common table expression; an absent list is empty.
     *
     * @return list<Name>
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Node $list): array
    {
        $form = $this->lowering->form($list);

        return match ($form->signature) {
            'opt_derived_column_list:' => [],
            'opt_derived_column_list: ( simple_ident_list )' => $this->names($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an optional table alias; no alias is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function alias(Node $alias): ?Name
    {
        $form = $this->lowering->form($alias);
        if ($form->signature === 'opt_table_alias: table_alias ident') {
            $keyword = $this->lowering->form($form->node(0));
            if (!in_array($keyword->signature, ['table_alias:', 'table_alias: AS', 'table_alias: EQ'], true)) {
                throw ImplementationGap::production($keyword);
            }

            return $this->lowering->names->identifier($form->node(1));
        }

        return match ($form->signature) {
            'opt_table_alias:' => null,
            'opt_table_alias: opt_as ident' => $this->lowering->names->identifier($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Answers what is written before an optional table alias; no alias answers AS, the mark of a table without alias.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function mark(Node $alias): AliasMark
    {
        $form = $this->lowering->form($alias);
        if ($form->signature === 'opt_table_alias:') {
            return AliasMark::As;
        }
        if ($form->signature !== 'opt_table_alias: table_alias ident' && $form->signature !== 'opt_table_alias: opt_as ident') {
            throw ImplementationGap::production($form);
        }
        $keyword = $this->lowering->form($form->node(0));

        return match ($keyword->signature) {
            'table_alias:', 'opt_as:' => AliasMark::Bare,
            'table_alias: AS', 'opt_as: AS' => AliasMark::As,
            'table_alias: EQ' => AliasMark::Equals,
            default => throw ImplementationGap::production($keyword),
        };
    }

    /**
     * Lowers the index hints of a table in written order.
     *
     * @return list<IndexHint>
     * @throws ImplementationGap When a production has no rule
     */
    public function hints(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_index_hints_list:') {
            return [];
        }
        if ($form->signature !== 'opt_index_hints_list: index_hints_list') {
            throw ImplementationGap::production($form);
        }
        $spine = $this->lowering->form($form->node(0));
        if ($spine->signature !== 'index_hints_list: index_hint_definition' && $spine->signature !== 'index_hints_list: index_hints_list index_hint_definition') {
            throw ImplementationGap::production($spine);
        }
        $hints = [];
        foreach ((new Lists())->items($spine->node) as $definition) {
            $hints[] = $this->hint($definition);
        }

        return $hints;
    }

    /**
     * Lowers one index hint.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function hint(Node $definition): IndexHint
    {
        $form = $this->lowering->form($definition);
        if ($form->signature === 'index_hint_definition: USE_SYM key_or_index index_hint_clause ( opt_key_usage_list )') {
            $action = IndexHintAction::Use;
        } elseif ($form->signature === 'index_hint_definition: index_hint_type key_or_index index_hint_clause ( key_usage_list )') {
            $type = $this->lowering->form($form->node(0));
            $action = match ($type->signature) {
                'index_hint_type: FORCE_SYM' => IndexHintAction::Force,
                'index_hint_type: IGNORE_SYM' => IndexHintAction::Ignore,
                default => throw ImplementationGap::production($type),
            };
        } else {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->skip($form->node(1));
        $scope = $this->lowering->form($form->node(2));
        if (!array_key_exists($scope->signature, self::SCOPES)) {
            throw ImplementationGap::production($scope);
        }

        return new IndexHint($action, self::SCOPES[$scope->signature], $this->indexes($form->node(4)));
    }

    /**
     * Lowers a list of index names, where PRIMARY names the primary key; an absent list is empty.
     *
     * @return list<Name|PrimaryIndex>
     * @throws ImplementationGap When a production has no rule
     */
    public function indexes(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_key_usage_list:') {
            return [];
        }
        if ($form->signature === 'opt_key_usage_list: key_usage_list') {
            return $this->indexes($form->node(0));
        }
        if ($form->signature !== 'key_usage_list: key_usage_element' && $form->signature !== 'key_usage_list: key_usage_list , key_usage_element') {
            throw ImplementationGap::production($form);
        }
        $indexes = [];
        foreach ((new Lists())->items($list) as $element) {
            $key = $this->lowering->form($element);
            $indexes[] = match ($key->signature) {
                'key_usage_element: ident' => $this->lowering->names->identifier($key->node(0)),
                'key_usage_element: PRIMARY_SYM' => new PrimaryIndex(),
                default => throw ImplementationGap::production($key),
            };
        }

        return $indexes;
    }

    /**
     * Lowers a TABLESAMPLE clause; an absent clause is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function sample(Node $clause): ?TableSample
    {
        $form = $this->lowering->form($clause);
        if ($form->signature === 'opt_tablesample_clause:') {
            return null;
        }
        if ($form->signature !== 'opt_tablesample_clause: TABLESAMPLE_SYM sampling_method ( sampling_percentage )') {
            throw ImplementationGap::production($form);
        }
        $method = $this->lowering->form($form->node(1));
        $percentage = $this->lowering->form($form->node(3));

        return new TableSample(match ($method->signature) {
            'sampling_method: SYSTEM_SYM' => SamplingMethod::System,
            'sampling_method: BERNOULLI_SYM' => SamplingMethod::Bernoulli,
            default => throw ImplementationGap::production($method),
        }, match ($percentage->signature) {
            'sampling_percentage: NUM_literal' => $this->lowering->literals->number($percentage->node(0)),
            'sampling_percentage: @ ident_or_text' => $this->lowering->variables->user($percentage->node(1)),
            'sampling_percentage: param_marker' => $this->lowering->literals->parameter($percentage->node(0)),
            default => throw ImplementationGap::production($percentage),
        });
    }
}
