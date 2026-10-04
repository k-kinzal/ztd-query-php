<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\MoveAll;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ParentTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachPartition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachMode;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachPartition;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the statements built on `AlterTableStmt` and the partition actions.
 *
 * Rule: PG-ALTER-TABLE-LOWER-001. Scope: `AlterTableStmt`,
 * `alter_table_cmds`, `partition_cmd`, `index_partition_cmd`. The actions
 * themselves are lowered by `AlterCommandRule`. A relation written as
 * `relation_expr` keeps ONLY; a `qualified_name` never has it. Termination:
 * lists are flattened iteratively. Source:
 * https://www.postgresql.org/docs/17/sql-altertable.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterTableRule
{
    /**
     * The kind, the relation position, the action position and IF EXISTS of each `AlterTableStmt` production with actions.
     */
    private const ALTER = [
        'AlterTableStmt: ALTER TABLE relation_expr alter_table_cmds' => [AlterTarget::Table, 2, 3, false],
        'AlterTableStmt: ALTER TABLE IF_P EXISTS relation_expr alter_table_cmds' => [AlterTarget::Table, 4, 5, true],
        'AlterTableStmt: ALTER TABLE relation_expr partition_cmd' => [AlterTarget::Table, 2, 3, false],
        'AlterTableStmt: ALTER TABLE IF_P EXISTS relation_expr partition_cmd' => [AlterTarget::Table, 4, 5, true],
        'AlterTableStmt: ALTER INDEX qualified_name alter_table_cmds' => [AlterTarget::Index, 2, 3, false],
        'AlterTableStmt: ALTER INDEX IF_P EXISTS qualified_name alter_table_cmds' => [AlterTarget::Index, 4, 5, true],
        'AlterTableStmt: ALTER INDEX qualified_name index_partition_cmd' => [AlterTarget::Index, 2, 3, false],
        'AlterTableStmt: ALTER SEQUENCE qualified_name alter_table_cmds' => [AlterTarget::Sequence, 2, 3, false],
        'AlterTableStmt: ALTER SEQUENCE IF_P EXISTS qualified_name alter_table_cmds' => [AlterTarget::Sequence, 4, 5, true],
        'AlterTableStmt: ALTER VIEW qualified_name alter_table_cmds' => [AlterTarget::View, 2, 3, false],
        'AlterTableStmt: ALTER VIEW IF_P EXISTS qualified_name alter_table_cmds' => [AlterTarget::View, 4, 5, true],
        'AlterTableStmt: ALTER MATERIALIZED VIEW qualified_name alter_table_cmds' => [AlterTarget::MaterializedView, 3, 4, false],
        'AlterTableStmt: ALTER MATERIALIZED VIEW IF_P EXISTS qualified_name alter_table_cmds' => [AlterTarget::MaterializedView, 5, 6, true],
        'AlterTableStmt: ALTER FOREIGN TABLE relation_expr alter_table_cmds' => [AlterTarget::ForeignTable, 3, 4, false],
        'AlterTableStmt: ALTER FOREIGN TABLE IF_P EXISTS relation_expr alter_table_cmds' => [AlterTarget::ForeignTable, 5, 6, true],
    ];

    /**
     * The kind and the position of the first tablespace name of each ALL IN TABLESPACE production, and whether OWNED BY is written.
     */
    private const MOVE = [
        'AlterTableStmt: ALTER TABLE ALL IN_P TABLESPACE name SET TABLESPACE name opt_nowait' => [AlterTarget::Table, 5, false],
        'AlterTableStmt: ALTER TABLE ALL IN_P TABLESPACE name OWNED BY role_list SET TABLESPACE name opt_nowait' => [AlterTarget::Table, 5, true],
        'AlterTableStmt: ALTER INDEX ALL IN_P TABLESPACE name SET TABLESPACE name opt_nowait' => [AlterTarget::Index, 5, false],
        'AlterTableStmt: ALTER INDEX ALL IN_P TABLESPACE name OWNED BY role_list SET TABLESPACE name opt_nowait' => [AlterTarget::Index, 5, true],
        'AlterTableStmt: ALTER MATERIALIZED VIEW ALL IN_P TABLESPACE name SET TABLESPACE name opt_nowait' => [AlterTarget::MaterializedView, 6, false],
        'AlterTableStmt: ALTER MATERIALIZED VIEW ALL IN_P TABLESPACE name OWNED BY role_list SET TABLESPACE name opt_nowait' => [AlterTarget::MaterializedView, 6, true],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `AlterTableStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->productions->form($statement);
        if (isset(self::MOVE[$form->signature])) {
            [$target, $at, $owned] = self::MOVE[$form->signature];
            $to = $owned ? $at + 6 : $at + 3;

            return new MoveAll(
                $target,
                $this->lowering->names->name($form->node($at)),
                $this->lowering->names->name($form->node($to)),
                $owned ? $this->lowering->roles->roles($form->node($at + 3)) : [],
                $this->lowering->flags->present($form->node($to + 1)),
            );
        }
        [$target, $relation, $commands, $ifExists] = self::ALTER[$form->signature] ?? throw ImplementationGap::production($form);
        $written = $form->node($relation);
        $reference = $written->name === 'qualified_name' ? new RelationReference($this->lowering->names->qualified($written)) : $this->lowering->queries->relation($written);

        return new AlterTable($target, $reference, $this->commands($form->node($commands)), $ifExists);
    }

    /**
     * Lowers `alter_table_cmds`, `partition_cmd` or `index_partition_cmd`.
     *
     * @return list<AlterCommand>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function commands(Node $list): array
    {
        if ($list->name !== 'alter_table_cmds') {
            return [$this->partition($list)];
        }
        $commands = [];
        $rule = new AlterCommandRule($this->lowering);
        foreach ($this->lowering->items($list, 'alter_table_cmds: alter_table_cmd', 'alter_table_cmds: alter_table_cmds , alter_table_cmd') as $item) {
            $commands[] = $rule->command($item);
        }

        return $commands;
    }

    /**
     * Lowers `partition_cmd` or `index_partition_cmd`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function partition(Node $command): AlterCommand
    {
        $form = $this->lowering->productions->form($command);
        $names = $this->lowering->names;

        return match ($form->signature) {
            'partition_cmd: ATTACH PARTITION qualified_name PartitionBoundSpec' => new AttachPartition(new ParentTable($names->qualified($form->node(2))), (new PartitionRule($this->lowering))->bound($form->node(3))),
            'partition_cmd: DETACH PARTITION qualified_name opt_concurrently' => new DetachPartition(new ParentTable($names->qualified($form->node(2))), $this->lowering->flags->present($form->node(3)) ? DetachMode::Concurrently : DetachMode::Plain),
            'partition_cmd: DETACH PARTITION qualified_name FINALIZE' => new DetachPartition(new ParentTable($names->qualified($form->node(2))), DetachMode::Finalize),
            'index_partition_cmd: ATTACH PARTITION qualified_name' => new AttachIndex($names->qualified($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }
}
