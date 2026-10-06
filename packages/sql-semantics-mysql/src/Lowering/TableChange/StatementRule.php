<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableChange;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\AlterRule;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\ModifierRule;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\PartitionRule;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TableRenaming;
use SqlSemantics\Platform\MySql\Statement\Alter\TargetTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the statements of the table change family other than the actions of ALTER TABLE.
 *
 * Rule: MYSQL-TABLE-CHANGE-STATEMENT-001. Scope: the DROP TABLE and DROP
 * INDEX alternatives of drop (5.6, 5.7), drop_table_stmt, drop_index_stmt,
 * rename, table_to_table_list, table_to_table, truncate, truncate_stmt,
 * opt_table_sym, partition_entry, and ALTER TABLE through
 * MYSQL-ALTER-STATEMENT-001. TABLE and TABLES are synonyms; the TABLE of
 * TRUNCATE is optional. RENAME USER belongs to the account family, which
 * lowers its list. Constructs: DropTable, DropIndex, RenameTable,
 * TableRenaming, TruncateTable, TargetTable, PartitionEntry. Terminates:
 * lists are flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/rename-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/truncate-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\TableChange
 */
final class StatementRule
{
    /**
     * The statement productions, by the statement they lower to.
     */
    private const STATEMENTS = [
        'drop: DROP opt_temporary table_or_tables if_exists table_list opt_restrict' => 'dropTable',
        'drop_table_stmt: DROP opt_temporary table_or_tables if_exists table_list opt_restrict' => 'dropTable',
        'drop: DROP INDEX_SYM ident ON table_ident opt_index_lock_algorithm' => 'dropIndex',
        'drop_index_stmt: DROP INDEX_SYM ident ON_SYM table_ident opt_index_lock_and_algorithm' => 'dropIndex',
        'rename: RENAME table_or_tables table_to_table_list' => 'rename',
        'rename: RENAME USER clear_privileges rename_list' => 'renameUsers',
        'rename: RENAME USER rename_list' => 'renameUsers',
        'truncate: TRUNCATE_SYM opt_table_sym table_name' => 'truncate',
        'truncate_stmt: TRUNCATE_SYM opt_table table_ident' => 'truncate',
        'partition_entry: PARTITION_SYM partition' => 'entry',
        'alter: ALTER opt_ignore TABLE_SYM table_ident alter_commands' => 'alter',
        'alter: ALTER TABLE_SYM table_ident alter_commands' => 'alter',
        'alter_table_stmt: ALTER TABLE_SYM table_ident opt_alter_table_actions' => 'alter',
        'alter_table_stmt: ALTER TABLE_SYM table_ident standalone_alter_table_action' => 'alter',
    ];

    /**
     * The productions of the optional TABLE of TRUNCATE.
     */
    private const TABLE_WORDS = ['opt_table_sym:' => true, 'opt_table_sym: TABLE_SYM' => true, 'opt_table:' => true, 'opt_table: TABLE_SYM' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one statement of the family.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        return match (self::STATEMENTS[$form->signature] ?? throw ImplementationGap::production($form)) {
            'dropTable' => $this->dropTable($form),
            'dropIndex' => new DropIndex(
                $this->lowering->names->identifier($form->node(2)),
                $this->lowering->names->qualified($form->node(4)),
                (new ModifierRule($this->lowering))->pair($form->node(5)),
            ),
            'rename' => $this->rename($form),
            'renameUsers' => $this->renameUsers($form),
            'truncate' => $this->truncate($form),
            'entry' => (new PartitionRule($this->lowering))->entry($form->node),
            'alter' => (new AlterRule($this->lowering))->statement($form),
        };
    }

    /**
     * Lowers DROP TABLE.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function dropTable(Form $form): DropTable
    {
        $this->lowering->options->skip($form->node(2));
        $tables = [];
        foreach ($this->lowering->names->qualifiedList($form->node(4)) as $name) {
            $tables[] = new TargetTable($name);
        }

        return new DropTable(
            $this->lowering->options->present($form->node(1)),
            $this->lowering->options->present($form->node(3)),
            $tables,
            $this->lowering->options->dropBehavior($form->node(5)),
        );
    }

    /**
     * Lowers RENAME TABLE.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function rename(Form $form): RenameTable
    {
        $this->lowering->options->skip($form->node(1));
        $list = $this->lowering->form($form->node(2));
        if ($list->signature !== 'table_to_table_list: table_to_table' && $list->signature !== 'table_to_table_list: table_to_table_list , table_to_table') {
            throw ImplementationGap::production($list);
        }
        $renamings = [];
        foreach ((new Lists())->items($list->node) as $item) {
            $pair = $this->lowering->form($item);
            if ($pair->signature !== 'table_to_table: table_ident TO_SYM table_ident') {
                throw ImplementationGap::production($pair);
            }
            $renamings[] = new TableRenaming($this->lowering->names->qualified($pair->node(0)), $this->lowering->names->qualified($pair->node(2)));
        }

        return new RenameTable($renamings);
    }

    /**
     * Hands RENAME USER to the account family.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function renameUsers(Form $form): Statement
    {
        if (count($form->node->children) === 4) {
            $this->lowering->options->skip($form->node(2));
        }

        return $this->lowering->accounts->renameUsers($form->node(count($form->node->children) - 1));
    }

    /**
     * Lowers TRUNCATE TABLE.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function truncate(Form $form): TruncateTable
    {
        $word = $this->lowering->form($form->node(1));
        if (!isset(self::TABLE_WORDS[$word->signature])) {
            throw ImplementationGap::production($word);
        }

        return new TruncateTable($this->lowering->names->qualified($form->node(2)));
    }
}
