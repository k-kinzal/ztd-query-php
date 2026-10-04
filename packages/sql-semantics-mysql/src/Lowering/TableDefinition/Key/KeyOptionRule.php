<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableDefinition\Spine;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexComment;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\KeyBlockSize;

/**
 * Lowers index types and index options.
 *
 * Rule: MYSQL-INDEX-OPTION-001. Scope: key_alg, key_using_alg,
 * btree_or_rtree, index_type, index_type_clause, opt_index_type_clause,
 * opt_index_name_and_type, normal_key_options, fulltext_key_options,
 * spatial_key_options, normal_key_opts, fulltext_key_opts,
 * spatial_key_opts, all_key_opt, normal_key_opt, fulltext_key_opt,
 * spatial_key_opt, opt_index_options, index_options, index_option,
 * opt_fulltext_index_options, fulltext_index_options,
 * fulltext_index_option, opt_spatial_index_options, spatial_index_options,
 * spatial_index_option, common_index_option, visibility. TYPE is a deprecated
 * synonym of USING. Constructs: IndexAlgorithm and the IndexOption
 * classes. Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/invisible-indexes.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class KeyOptionRule
{
    /**
     * The index structures.
     */
    private const ALGORITHMS = [
        'btree_or_rtree: BTREE_SYM' => IndexAlgorithm::Btree, 'btree_or_rtree: RTREE_SYM' => IndexAlgorithm::Rtree, 'btree_or_rtree: HASH_SYM' => IndexAlgorithm::Hash,
        'index_type: BTREE_SYM' => IndexAlgorithm::Btree, 'index_type: RTREE_SYM' => IndexAlgorithm::Rtree, 'index_type: HASH_SYM' => IndexAlgorithm::Hash,
    ];

    /**
     * The productions of the option lists, with the empty and the unit productions that lead to them.
     */
    private const LISTS = [
        'normal_key_opts: normal_key_opt', 'normal_key_opts: normal_key_opts normal_key_opt', 'fulltext_key_opts: fulltext_key_opt',
        'fulltext_key_opts: fulltext_key_opts fulltext_key_opt', 'spatial_key_opts: spatial_key_opt', 'spatial_key_opts: spatial_key_opts spatial_key_opt',
        'index_options: index_option', 'index_options: index_options index_option', 'fulltext_index_options: fulltext_index_option',
        'fulltext_index_options: fulltext_index_options fulltext_index_option', 'spatial_index_options: spatial_index_option',
        'spatial_index_options: spatial_index_options spatial_index_option',
    ];

    /**
     * The optional option list productions: empty, or the list.
     */
    private const OPTIONAL = [
        'normal_key_options:' => false, 'normal_key_options: normal_key_opts' => true, 'fulltext_key_options:' => false,
        'fulltext_key_options: fulltext_key_opts' => true, 'spatial_key_options:' => false, 'spatial_key_options: spatial_key_opts' => true,
        'opt_index_options:' => false, 'opt_index_options: index_options' => true, 'opt_fulltext_index_options:' => false,
        'opt_fulltext_index_options: fulltext_index_options' => true, 'opt_spatial_index_options:' => false,
        'opt_spatial_index_options: spatial_index_options' => true,
    ];

    /**
     * The unit productions of one option that pass it on to their only child.
     */
    private const FORWARD = [
        'normal_key_opt: all_key_opt' => true, 'normal_key_opt: key_using_alg' => true, 'spatial_key_opt: all_key_opt' => true,
        'fulltext_key_opt: all_key_opt' => true, 'index_option: common_index_option' => true, 'index_option: index_type_clause' => true,
        'fulltext_index_option: common_index_option' => true, 'spatial_index_option: common_index_option' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an index structure clause; an absent clause is null: a node of `key_alg`, `key_using_alg`, `opt_index_type_clause` or `index_type_clause`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function algorithm(Node $clause): ?IndexAlgorithm
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'key_alg: init_key_options' => $this->marked($form->node(0), null),
            'key_alg: init_key_options key_using_alg' => $this->marked($form->node(0), $this->algorithm($form->node(1))),
            'opt_index_type_clause:' => null,
            'opt_index_type_clause: index_type_clause' => $this->algorithm($form->node(0)),
            'key_using_alg: USING btree_or_rtree', 'key_using_alg: TYPE_SYM btree_or_rtree', 'index_type_clause: USING index_type',
            'index_type_clause: TYPE_SYM index_type' => $this->structure($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Confirms the empty marker rule `init_key_options` and answers the index structure that follows it.
     *
     * @throws ImplementationGap When the marker matches another production
     */
    public function marked(Node $marker, ?IndexAlgorithm $algorithm): ?IndexAlgorithm
    {
        $this->lowering->options->skip($marker);

        return $algorithm;
    }

    /**
     * Lowers the index name and the USING clause before the key parts: a node of `opt_index_name_and_type`.
     *
     * @return array{ColumnName|null, IndexAlgorithm|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function nameAndType(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'opt_index_name_and_type: opt_ident' => [$this->lowering->names->optionalColumnName($form->node(0)), null],
            'opt_index_name_and_type: opt_ident USING index_type' => [$this->lowering->names->optionalColumnName($form->node(0)), $this->structure($form->node(2))],
            'opt_index_name_and_type: ident TYPE_SYM index_type' => [$this->lowering->leaves->record(new ColumnName($this->lowering->names->identifier($form->node(0)))), $this->structure($form->node(2))],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an index structure keyword: a node of `index_type` or `btree_or_rtree`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function structure(Node $structure): IndexAlgorithm
    {
        $form = $this->lowering->productions->form($structure);

        return self::ALGORITHMS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers the options after the key parts: a node of one of the optional option list rules.
     *
     * @return list<IndexOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $options): array
    {
        $form = $this->lowering->productions->form($options);
        $written = self::OPTIONAL[$form->signature] ?? throw ImplementationGap::production($form);
        if (!$written) {
            return [];
        }
        $list = $form->node(0);
        $items = [];
        foreach ((new Spine($this->lowering))->items($list, self::LISTS, ['normal_key_opt', 'fulltext_key_opt', 'spatial_key_opt', 'index_option', 'fulltext_index_option', 'spatial_index_option']) as $item) {
            $items[] = $this->option($item);
        }

        return $items;
    }

    /**
     * Lowers one index option.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function option(Node $option): IndexOption
    {
        $form = $this->lowering->productions->form($option);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'all_key_opt: KEY_BLOCK_SIZE opt_equal ulong_num', 'common_index_option: KEY_BLOCK_SIZE opt_equal ulong_num' => new KeyBlockSize($this->lowering->numbers->numeral($form->node(2))),
            'all_key_opt: COMMENT_SYM TEXT_STRING_sys', 'common_index_option: COMMENT_SYM TEXT_STRING_sys' => new IndexComment($literals->text($form->node(1))),
            'common_index_option: visibility' => new IndexVisibility($this->visible($form->node(0))),
            'common_index_option: ENGINE_ATTRIBUTE_SYM opt_equal json_attribute' => new IndexEngineAttribute($literals->text($form->node(2))),
            'common_index_option: SECONDARY_ENGINE_ATTRIBUTE_SYM opt_equal json_attribute' => new IndexEngineAttribute($literals->text($form->node(2)), true),
            'fulltext_key_opt: WITH PARSER_SYM IDENT_sys', 'fulltext_index_option: WITH PARSER_SYM IDENT_sys' => new IndexParser($this->lowering->names->identifier($form->node(2))),
            'key_using_alg: USING btree_or_rtree', 'key_using_alg: TYPE_SYM btree_or_rtree', 'index_type_clause: USING index_type', 'index_type_clause: TYPE_SYM index_type' => new IndexUsing($this->structure($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Tells whether VISIBLE is written: a node of `visibility`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function visible(Node $visibility): bool
    {
        $form = $this->lowering->productions->form($visibility);

        return match ($form->signature) {
            'visibility: VISIBLE_SYM' => true,
            'visibility: INVISIBLE_SYM' => false,
            default => throw ImplementationGap::production($form),
        };
    }
}
