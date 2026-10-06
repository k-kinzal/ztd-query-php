<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Alter;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\DefinitionRule;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\DropPartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ExchangePartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintainPartitions;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\MaintenanceKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ReorganizePartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\StandaloneCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\TablespaceCommand;
use SqlSemantics\Platform\MySql\Statement\Partition\AllPartitions;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;

/**
 * Lowers the partition and tablespace operations of ALTER TABLE.
 *
 * Rule: MYSQL-ALTER-STANDALONE-001. Scope: the partition and tablespace
 * alternatives of alter_commands (5.6), standalone_alter_commands,
 * add_partition_rule, add_part_extra, reorg_partition_rule,
 * reorg_parts_rule, alt_part_name_list, alt_part_name_item,
 * all_or_alt_part_name_list. LOCAL and NO_WRITE_TO_BINLOG are synonyms. The
 * CHECK and REPAIR options are lowered by the server family. Constructs:
 * TablespaceCommand, AddPartition, DropPartition, MaintainPartitions,
 * CoalescePartition, ReorganizePartition, ExchangePartition,
 * SecondaryLoad, AllPartitions, NamedPartitions. Terminates: lists are
 * flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table-partition-operations.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class StandaloneRule
{
    /**
     * The tablespace productions, by whether they import and the position of the partitions.
     */
    private const TABLESPACES = [
        'alter_commands: DISCARD TABLESPACE' => [false, null], 'alter_commands: IMPORT TABLESPACE' => [true, null],
        'standalone_alter_commands: DISCARD TABLESPACE_SYM' => [false, null], 'standalone_alter_commands: DISCARD_SYM TABLESPACE_SYM' => [false, null],
        'standalone_alter_commands: IMPORT TABLESPACE_SYM' => [true, null],
        'standalone_alter_commands: DISCARD PARTITION_SYM all_or_alt_part_name_list TABLESPACE_SYM' => [false, 2],
        'standalone_alter_commands: DISCARD_SYM PARTITION_SYM all_or_alt_part_name_list TABLESPACE_SYM' => [false, 2],
        'standalone_alter_commands: IMPORT PARTITION_SYM all_or_alt_part_name_list TABLESPACE_SYM' => [true, 2],
    ];

    /**
     * The maintenance productions, by the operation and the positions of NO_WRITE_TO_BINLOG, the partitions, the options and the ignored option.
     */
    private const MAINTENANCE = [
        'alter_commands: REBUILD_SYM PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list' => [MaintenanceKind::Rebuild, 2, 3, null, null],
        'standalone_alter_commands: REBUILD_SYM PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list' => [MaintenanceKind::Rebuild, 2, 3, null, null],
        'alter_commands: OPTIMIZE PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list opt_no_write_to_binlog' => [MaintenanceKind::Optimize, 2, 3, null, 4],
        'standalone_alter_commands: OPTIMIZE PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list opt_no_write_to_binlog' => [MaintenanceKind::Optimize, 2, 3, null, 4],
        'standalone_alter_commands: OPTIMIZE PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list' => [MaintenanceKind::Optimize, 2, 3, null, null],
        'alter_commands: ANALYZE_SYM PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list' => [MaintenanceKind::Analyze, 2, 3, null, null],
        'standalone_alter_commands: ANALYZE_SYM PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list' => [MaintenanceKind::Analyze, 2, 3, null, null],
        'alter_commands: CHECK_SYM PARTITION_SYM all_or_alt_part_name_list opt_mi_check_type' => [MaintenanceKind::Check, null, 2, 3, null],
        'standalone_alter_commands: CHECK_SYM PARTITION_SYM all_or_alt_part_name_list opt_mi_check_type' => [MaintenanceKind::Check, null, 2, 3, null],
        'standalone_alter_commands: CHECK_SYM PARTITION_SYM all_or_alt_part_name_list opt_mi_check_types' => [MaintenanceKind::Check, null, 2, 3, null],
        'alter_commands: REPAIR PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list opt_mi_repair_type' => [MaintenanceKind::Repair, 2, 3, 4, null],
        'standalone_alter_commands: REPAIR PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list opt_mi_repair_type' => [MaintenanceKind::Repair, 2, 3, 4, null],
        'standalone_alter_commands: REPAIR PARTITION_SYM opt_no_write_to_binlog all_or_alt_part_name_list opt_mi_repair_types' => [MaintenanceKind::Repair, 2, 3, 4, null],
        'alter_commands: TRUNCATE_SYM PARTITION_SYM all_or_alt_part_name_list' => [MaintenanceKind::Truncate, null, 2, null, null],
        'standalone_alter_commands: TRUNCATE_SYM PARTITION_SYM all_or_alt_part_name_list' => [MaintenanceKind::Truncate, null, 2, null, null],
    ];

    /**
     * The productions whose operation reads its operands from one position.
     */
    private const SINGLES = [
        'alter_commands: add_partition_rule' => ['addRule', 0], 'standalone_alter_commands: add_partition_rule' => ['addRule', 0],
        'alter_commands: reorg_partition_rule' => ['reorganizeRule', 0], 'standalone_alter_commands: reorg_partition_rule' => ['reorganizeRule', 0],
        'standalone_alter_commands: ADD PARTITION_SYM opt_no_write_to_binlog' => ['add', 2],
        'standalone_alter_commands: REORGANIZE_SYM PARTITION_SYM opt_no_write_to_binlog' => ['reorganize', 2],
        'alter_commands: DROP PARTITION_SYM alt_part_name_list' => ['drop', 2], 'standalone_alter_commands: DROP PARTITION_SYM alt_part_name_list' => ['drop', 2],
        'standalone_alter_commands: DROP PARTITION_SYM ident_string_list' => ['drop', 2],
        'standalone_alter_commands: SECONDARY_LOAD_SYM opt_use_partition' => ['load', 1], 'standalone_alter_commands: SECONDARY_UNLOAD_SYM opt_use_partition' => ['unload', 1],
    ];

    /**
     * The productions whose operation reads its operands from two positions.
     */
    private const PAIRS = [
        'standalone_alter_commands: ADD PARTITION_SYM opt_no_write_to_binlog ( part_def_list )' => ['addDefinitions', 2, 4],
        'standalone_alter_commands: ADD PARTITION_SYM opt_no_write_to_binlog PARTITIONS_SYM real_ulong_num' => ['addCount', 2, 4],
        'alter_commands: COALESCE PARTITION_SYM opt_no_write_to_binlog real_ulong_num' => ['coalesce', 2, 3],
        'standalone_alter_commands: COALESCE PARTITION_SYM opt_no_write_to_binlog real_ulong_num' => ['coalesce', 2, 3],
        'standalone_alter_commands: REORGANIZE_SYM PARTITION_SYM opt_no_write_to_binlog ident_string_list INTO ( part_def_list )' => ['reorganizeInto', 2, 3],
        'alter_commands: EXCHANGE_SYM PARTITION_SYM alt_part_name_item WITH TABLE_SYM table_ident have_partitioning' => ['exchange', 2, 5],
        'standalone_alter_commands: EXCHANGE_SYM PARTITION_SYM alt_part_name_item WITH TABLE_SYM table_ident opt_validation' => ['exchange', 2, 5],
        'standalone_alter_commands: EXCHANGE_SYM PARTITION_SYM ident WITH TABLE_SYM table_ident opt_with_validation' => ['exchange', 2, 5],
    ];

    /**
     * The secondary engine productions without partitions, by whether they load.
     */
    private const SECONDARY = ['standalone_alter_commands: SECONDARY_LOAD_SYM' => true, 'standalone_alter_commands: SECONDARY_UNLOAD_SYM' => false];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether a production of `alter_commands` or `standalone_alter_commands` is a partition or tablespace operation.
     */
    public function claims(string $signature): bool
    {
        return isset(self::TABLESPACES[$signature]) || isset(self::MAINTENANCE[$signature]) || isset(self::SINGLES[$signature]) || isset(self::PAIRS[$signature])
            || isset(self::SECONDARY[$signature]);
    }

    /**
     * Lowers a partition or tablespace operation.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function command(Form $form): StandaloneCommand
    {
        if (isset(self::TABLESPACES[$form->signature])) {
            [$import, $partitions] = self::TABLESPACES[$form->signature];

            return new TablespaceCommand($import, $partitions === null ? null : $this->selection($form->node($partitions)));
        }
        if (isset(self::MAINTENANCE[$form->signature])) {
            return $this->maintain($form);
        }
        if (isset(self::SECONDARY[$form->signature])) {
            return new SecondaryLoad(self::SECONDARY[$form->signature]);
        }
        if (isset(self::PAIRS[$form->signature])) {
            return $this->pair($form);
        }
        [$kind, $position] = self::SINGLES[$form->signature] ?? throw ImplementationGap::production($form);
        $operand = $form->node($position);

        return match ($kind) {
            'addRule' => $this->addRule($operand),
            'reorganizeRule' => $this->reorganizeRule($operand),
            'add' => new AddPartition($this->local($operand), []),
            'reorganize' => new ReorganizePartition($this->local($operand)),
            'drop' => new DropPartition($this->names($operand)),
            'load', 'unload' => new SecondaryLoad($kind === 'load', $this->lowering->queries->partitions($operand)),
        };
    }

    /**
     * Lowers an operation whose operands are at two positions.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function pair(Form $form): StandaloneCommand
    {
        [$kind, $first, $second] = self::PAIRS[$form->signature] ?? throw ImplementationGap::production($form);
        $definitions = new DefinitionRule($this->lowering);

        return match ($kind) {
            'addDefinitions' => new AddPartition($this->local($form->node($first)), $definitions->definitions($form->node($second))),
            'addCount' => new AddPartition($this->local($form->node($first)), [], $this->lowering->numbers->numeral($form->node($second))),
            'coalesce' => new CoalescePartition($this->local($form->node($first)), $this->lowering->numbers->numeral($form->node($second))),
            'reorganizeInto' => new ReorganizePartition($this->local($form->node($first)), $this->names($form->node($second)), $definitions->definitions($form->node($second + 3))),
            'exchange' => $this->exchange($form, $first, $second),
        };
    }

    /**
     * Lowers a maintenance operation on partitions.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function maintain(Form $form): MaintainPartitions
    {
        [$kind, $local, $partitions, $options, $ignored] = self::MAINTENANCE[$form->signature];
        $server = $this->lowering->server;
        $lowered = [];
        if ($options !== null) {
            $lowered = $kind === MaintenanceKind::Repair ? $server->repairOptions($form->node($options)) : $server->checkOptions($form->node($options));
        }

        return new MaintainPartitions(
            $kind,
            $local !== null && $this->local($form->node($local)),
            $this->selection($form->node($partitions)),
            $lowered,
            $ignored !== null && $this->local($form->node($ignored)),
        );
    }

    /**
     * Lowers NO_WRITE_TO_BINLOG or LOCAL: a node of `opt_no_write_to_binlog`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function local(Node $option): bool
    {
        return $this->lowering->options->present($option);
    }

    /**
     * Lowers the 5.x ADD PARTITION: a node of `add_partition_rule`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function addRule(Node $rule): AddPartition
    {
        $form = $this->lowering->form($rule);
        if ($form->signature !== 'add_partition_rule: ADD PARTITION_SYM opt_no_write_to_binlog add_part_extra') {
            throw ImplementationGap::production($form);
        }
        $local = $this->local($form->node(2));
        $extra = $this->lowering->form($form->node(3));

        return match ($extra->signature) {
            'add_part_extra:' => new AddPartition($local, []),
            'add_part_extra: ( part_def_list )' => new AddPartition($local, (new DefinitionRule($this->lowering))->definitions($extra->node(1))),
            'add_part_extra: PARTITIONS_SYM real_ulong_num' => new AddPartition($local, [], $this->lowering->numbers->numeral($extra->node(1))),
            default => throw ImplementationGap::production($extra),
        };
    }

    /**
     * Lowers the 5.x REORGANIZE PARTITION: a node of `reorg_partition_rule`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function reorganizeRule(Node $rule): ReorganizePartition
    {
        $form = $this->lowering->form($rule);
        if ($form->signature !== 'reorg_partition_rule: REORGANIZE_SYM PARTITION_SYM opt_no_write_to_binlog reorg_parts_rule') {
            throw ImplementationGap::production($form);
        }
        $local = $this->local($form->node(2));
        $parts = $this->lowering->form($form->node(3));

        return match ($parts->signature) {
            'reorg_parts_rule:' => new ReorganizePartition($local),
            'reorg_parts_rule: alt_part_name_list INTO ( part_def_list )' => new ReorganizePartition(
                $local,
                $this->names($parts->node(0)),
                (new DefinitionRule($this->lowering))->definitions($parts->node(3)),
            ),
            default => throw ImplementationGap::production($parts),
        };
    }

    /**
     * Lowers EXCHANGE PARTITION.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function exchange(Form $form, int $partition, int $table): ExchangePartition
    {
        $name = $form->node($partition);
        $partitionName = $this->lowering->names->identifier($name->name === 'alt_part_name_item' ? $this->item($name) : $name);
        $last = $form->node(6);
        if ($last->name === 'have_partitioning') {
            $this->lowering->options->skip($last);
            $validation = null;
        } else {
            $validation = (new ModifierRule($this->lowering))->validation($last);
        }

        return new ExchangePartition($partitionName, $this->lowering->names->qualified($form->node($table)), $validation);
    }

    /**
     * Lowers the partitions an operation names: a node of `all_or_alt_part_name_list`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function selection(Node $list): PartitionSelection
    {
        $form = $this->lowering->form($list);

        return match ($form->signature) {
            'all_or_alt_part_name_list: ALL' => new AllPartitions(),
            'all_or_alt_part_name_list: alt_part_name_list', 'all_or_alt_part_name_list: ident_string_list' => $this->names($form->node(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a list of partition names: a node of `alt_part_name_list` or `ident_string_list`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function names(Node $list): NamedPartitions
    {
        if ($list->name === 'ident_string_list') {
            return new NamedPartitions($this->lowering->names->identifiers($list));
        }
        $form = $this->lowering->form($list);
        if ($form->signature !== 'alt_part_name_list: alt_part_name_item' && $form->signature !== 'alt_part_name_list: alt_part_name_list , alt_part_name_item') {
            throw ImplementationGap::production($form);
        }
        $names = [];
        foreach ((new Lists())->items($list) as $item) {
            $names[] = $this->lowering->names->identifier($this->item($item));
        }

        return new NamedPartitions($names);
    }

    /**
     * Answers the name node of one partition name: a node of `alt_part_name_item`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function item(Node $item): Node
    {
        $form = $this->lowering->form($item);
        if ($form->signature !== 'alt_part_name_item: ident') {
            throw ImplementationGap::production($form);
        }

        return $form->node(0);
    }
}
