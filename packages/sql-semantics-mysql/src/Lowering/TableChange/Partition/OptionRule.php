<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Partition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOption;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind;

/**
 * Lowers the options of partition and subpartition definitions.
 *
 * Rule: MYSQL-PARTITION-OPTION-001. Scope: opt_part_options,
 * opt_part_option_list, opt_part_option, part_option_list, part_option. The
 * `=` (opt_equal) and the STORAGE before ENGINE (opt_storage) are optional
 * words of the same option. Constructs: PartitionOption. Terminates: the
 * option list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-partitioning.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class OptionRule
{
    /**
     * The option productions, by the option and the positions of the optional words and of the value.
     */
    private const OPTIONS = [
        'opt_part_option: TABLESPACE opt_equal ident_or_text' => [PartitionOptionKind::Tablespace, null, 1, 2],
        'opt_part_option: TABLESPACE_SYM opt_equal ident' => [PartitionOptionKind::Tablespace, null, 1, 2],
        'part_option: TABLESPACE_SYM opt_equal ident' => [PartitionOptionKind::Tablespace, null, 1, 2],
        'opt_part_option: opt_storage ENGINE_SYM opt_equal storage_engines' => [PartitionOptionKind::Engine, 0, 2, 3],
        'part_option: opt_storage ENGINE_SYM opt_equal ident_or_text' => [PartitionOptionKind::Engine, 0, 2, 3],
        'opt_part_option: NODEGROUP_SYM opt_equal real_ulong_num' => [PartitionOptionKind::NodeGroup, null, 1, 2],
        'part_option: NODEGROUP_SYM opt_equal real_ulong_num' => [PartitionOptionKind::NodeGroup, null, 1, 2],
        'opt_part_option: MAX_ROWS opt_equal real_ulonglong_num' => [PartitionOptionKind::MaxRows, null, 1, 2],
        'part_option: MAX_ROWS opt_equal real_ulonglong_num' => [PartitionOptionKind::MaxRows, null, 1, 2],
        'opt_part_option: MIN_ROWS opt_equal real_ulonglong_num' => [PartitionOptionKind::MinRows, null, 1, 2],
        'part_option: MIN_ROWS opt_equal real_ulonglong_num' => [PartitionOptionKind::MinRows, null, 1, 2],
        'opt_part_option: DATA_SYM DIRECTORY_SYM opt_equal TEXT_STRING_sys' => [PartitionOptionKind::DataDirectory, null, 2, 3],
        'part_option: DATA_SYM DIRECTORY_SYM opt_equal TEXT_STRING_sys' => [PartitionOptionKind::DataDirectory, null, 2, 3],
        'opt_part_option: INDEX_SYM DIRECTORY_SYM opt_equal TEXT_STRING_sys' => [PartitionOptionKind::IndexDirectory, null, 2, 3],
        'part_option: INDEX_SYM DIRECTORY_SYM opt_equal TEXT_STRING_sys' => [PartitionOptionKind::IndexDirectory, null, 2, 3],
        'opt_part_option: COMMENT_SYM opt_equal TEXT_STRING_sys' => [PartitionOptionKind::Comment, null, 1, 2],
        'part_option: COMMENT_SYM opt_equal TEXT_STRING_sys' => [PartitionOptionKind::Comment, null, 1, 2],
    ];

    /**
     * The productions that hold the option list or nothing.
     */
    private const LISTS = [
        'opt_part_options:' => false, 'opt_part_options: opt_part_option_list' => true, 'opt_part_options: part_option_list' => true,
    ];

    /**
     * The list spines of options.
     */
    private const SPINES = [
        'opt_part_option_list: opt_part_option_list opt_part_option' => true, 'opt_part_option_list: opt_part_option' => true,
        'part_option_list: part_option_list part_option' => true, 'part_option_list: part_option' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the options of a definition: a node of `opt_part_options`; no option is empty.
     *
     * @return list<PartitionOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $options): array
    {
        $form = $this->lowering->form($options);
        $holds = self::LISTS[$form->signature] ?? throw ImplementationGap::production($form);
        if (!$holds) {
            return [];
        }
        $spine = $this->lowering->form($form->node(0));
        if (!isset(self::SPINES[$spine->signature])) {
            throw ImplementationGap::production($spine);
        }
        $lowered = [];
        foreach ((new Lists())->items($form->node(0)) as $item) {
            $lowered[] = $this->option($item);
        }

        return $lowered;
    }

    /**
     * Lowers one option: a node of `opt_part_option` or `part_option`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): PartitionOption
    {
        $form = $this->lowering->form($option);
        [$kind, $storage, $equal, $value] = self::OPTIONS[$form->signature] ?? throw ImplementationGap::production($form);
        if ($storage !== null) {
            $this->lowering->options->present($form->node($storage));
        }
        $this->lowering->options->present($form->node($equal));
        if ($kind->named()) {
            return new PartitionOption($kind, $this->lowering->names->identifier($form->node($value)));
        }
        if ($kind->numbered()) {
            return new PartitionOption($kind, number: $this->lowering->numbers->numeral($form->node($value)));
        }

        return new PartitionOption($kind, text: $this->lowering->literals->text($form->node($value)));
    }
}
