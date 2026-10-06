<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange\Alter;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\PartitionRule;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\SetTableOptions;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\RemovePartitioning;

/**
 * Lowers ALTER TABLE: the statement and the lists that hold its actions.
 *
 * Rule: MYSQL-ALTER-STATEMENT-001. Scope: the ALTER TABLE alternatives of
 * alter (5.6, 5.7), alter_table_stmt, alter_commands, alter_command_list,
 * opt_alter_table_actions, standalone_alter_table_action,
 * alter_table_partition_options, opt_alter_command_list, alter_list,
 * remove_partitioning. The actions are flattened into one list in the order
 * written; a run of table options written without commas is one action.
 * Constructs: AlterTable, SetTableOptions, PartitionBy, RemovePartitioning.
 * Terminates: lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class AlterRule
{
    /**
     * The productions that hold action lists, by the positions of the parts in order.
     */
    private const PARTS = [
        'alter_commands:' => [], 'alter_command_list:' => [], 'opt_alter_command_list:' => [],
        'alter_commands: alter_list opt_partitioning' => [0, 1], 'alter_commands: alter_list remove_partitioning' => [0, 1],
        'alter_commands: remove_partitioning' => [0], 'alter_commands: partitioning' => [0],
        'alter_commands: alter_command_list' => [0], 'alter_commands: alter_command_list partitioning' => [0, 1],
        'alter_commands: alter_command_list remove_partitioning' => [0, 1], 'alter_commands: standalone_alter_commands' => [0],
        'alter_commands: alter_commands_modifier_list , standalone_alter_commands' => [0, 2],
        'alter_command_list: alter_commands_modifier_list' => [0], 'alter_command_list: alter_list' => [0],
        'alter_command_list: alter_commands_modifier_list , alter_list' => [0, 2],
        'opt_alter_table_actions: opt_alter_command_list' => [0], 'opt_alter_table_actions: opt_alter_command_list alter_table_partition_options' => [0, 1],
        'standalone_alter_table_action: standalone_alter_commands' => [0],
        'standalone_alter_table_action: alter_commands_modifier_list , standalone_alter_commands' => [0, 2],
        'opt_alter_command_list: alter_commands_modifier_list' => [0], 'opt_alter_command_list: alter_list' => [0],
        'opt_alter_command_list: alter_commands_modifier_list , alter_list' => [0, 2],
    ];

    /**
     * The productions of the action list spine.
     */
    private const LISTS = [
        'alter_list: alter_list_item' => true, 'alter_list: alter_list , alter_list_item' => true, 'alter_list: alter_list , alter_commands_modifier' => true,
        'alter_list: create_table_options_space_separated' => true, 'alter_list: alter_list , create_table_options_space_separated' => true,
    ];

    /**
     * The productions that remove the partitioning.
     */
    private const REMOVALS = [
        'remove_partitioning: REMOVE_SYM PARTITIONING_SYM have_partitioning' => true, 'remove_partitioning: REMOVE_SYM PARTITIONING_SYM' => true,
        'alter_table_partition_options: REMOVE_SYM PARTITIONING_SYM' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers ALTER TABLE: a production of `alter` routed to this family, or a node of `alter_table_stmt`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): AlterTable
    {
        return match ($form->signature) {
            'alter: ALTER opt_ignore TABLE_SYM table_ident alter_commands' => new AlterTable(
                $this->lowering->names->qualified($form->node(3)),
                $this->commands($form->node(4)),
                $this->lowering->options->present($form->node(1)),
            ),
            'alter: ALTER TABLE_SYM table_ident alter_commands',
            'alter_table_stmt: ALTER TABLE_SYM table_ident opt_alter_table_actions',
            'alter_table_stmt: ALTER TABLE_SYM table_ident standalone_alter_table_action' => new AlterTable(
                $this->lowering->names->qualified($form->node(2)),
                $this->commands($form->node(3)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the actions a node holds, in the order written.
     *
     * @return list<AlterCommand>
     * @throws ImplementationGap When a production has no rule
     */
    public function commands(Node $node): array
    {
        $form = $this->lowering->form($node);
        if (isset(self::PARTS[$form->signature])) {
            $commands = [];
            foreach (self::PARTS[$form->signature] as $position) {
                $commands = [...$commands, ...$this->commands($form->node($position))];
            }

            return $commands;
        }
        $standalone = new StandaloneRule($this->lowering);
        if ($standalone->claims($form->signature)) {
            return [$standalone->command($form)];
        }
        if (isset(self::REMOVALS[$form->signature])) {
            if (count($form->node->children) === 3) {
                $this->lowering->options->skip($form->node(2));
            }

            return [new RemovePartitioning()];
        }

        return $this->others($form);
    }

    /**
     * Lowers the action lists that are not a sequence of parts: the list spine, modifiers and partitioning.
     *
     * @return list<AlterCommand>
     * @throws ImplementationGap When a production has no rule
     */
    public function others(Form $form): array
    {
        if (isset(self::LISTS[$form->signature])) {
            return $this->items($form->node);
        }
        if ($form->node->name === 'alter_commands_modifier_list') {
            return (new ModifierRule($this->lowering))->modifiers($form->node);
        }
        $partitioning = match ($form->signature) {
            'alter_table_partition_options: partition_clause' => $form->node(0),
            'opt_partitioning:', 'opt_partitioning: partitioning', 'partitioning: PARTITION_SYM have_partitioning partition',
            'partitioning: PARTITION_SYM partition' => $form->node,
            default => throw ImplementationGap::production($form),
        };
        $clause = (new PartitionRule($this->lowering))->partitioning($partitioning);

        return $clause === null ? [] : [new PartitionBy($clause)];
    }

    /**
     * Lowers the items of the action list spine: actions, modifiers and runs of table options.
     *
     * @return list<AlterCommand>
     * @throws ImplementationGap When a production has no rule
     */
    public function items(Node $list): array
    {
        $commands = [];
        foreach ((new Lists())->items($list) as $item) {
            $commands[] = match ($item->name) {
                'alter_list_item' => (new ItemRule($this->lowering))->item($item),
                'alter_commands_modifier' => (new ModifierRule($this->lowering))->modifier($item),
                default => new SetTableOptions($this->lowering->tableDefinitions->tableOptions($item)),
            };
        }

        return $commands;
    }
}
