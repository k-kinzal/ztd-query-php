<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Transaction;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockInstance;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockInstance;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables;
use SqlSemantics\Statement\Statement;

/**
 * Lowers LOCK TABLES, UNLOCK TABLES and the backup lock statements.
 *
 * Rule: MYSQL-LOCK-001. Scope: lock, unlock, table_lock_list, table_lock,
 * lock_option. TABLE and TABLES are synonyms (LeafNoise); the alias goes
 * through the query family's alias rule. Constructs: LockTables, TableLock,
 * UnlockTables, LockInstance, UnlockInstance. Terminates: the lock list is
 * flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/lock-instance-for-backup.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class LockRule
{
    /**
     * The lock productions, by the lock they take.
     */
    private const MODES = [
        'lock_option: READ_SYM' => LockMode::Read, 'lock_option: READ_SYM LOCAL_SYM' => LockMode::ReadLocal,
        'lock_option: WRITE_SYM' => LockMode::Write, 'lock_option: LOW_PRIORITY WRITE_SYM' => LockMode::LowPriorityWrite,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a lock statement: a node of `lock` or `unlock`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        return match ($form->signature) {
            'lock: LOCK_SYM table_or_tables table_lock_list' => $this->tables($form),
            'lock: LOCK_SYM INSTANCE_SYM FOR_SYM BACKUP_SYM' => new LockInstance(),
            'unlock: UNLOCK_SYM table_or_tables' => $this->unlock($form->node(1)),
            'unlock: UNLOCK_SYM INSTANCE_SYM' => new UnlockInstance(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers LOCK TABLES.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function tables(Form $form): LockTables
    {
        $this->lowering->options->skip($form->node(1));
        $list = $form->node(2);
        $this->lowering->names->claimed($this->lowering->form($list), ['table_lock_list: table_lock', 'table_lock_list: table_lock_list , table_lock']);
        $locks = [];
        foreach ((new Lists())->items($list) as $item) {
            $locks[] = $this->lock($item);
        }

        return new LockTables($locks);
    }

    /**
     * Lowers one locked table: a node of `table_lock`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function lock(Node $lock): TableLock
    {
        $form = $this->lowering->form($lock);
        if ($form->signature !== 'table_lock: table_ident opt_table_alias lock_option') {
            throw ImplementationGap::production($form);
        }
        $mode = $this->lowering->form($form->node(2));

        return new TableLock(
            $this->lowering->names->qualified($form->node(0)),
            $this->lowering->queries->alias($form->node(1)),
            self::MODES[$mode->signature] ?? throw ImplementationGap::production($mode),
            $this->lowering->queries->mark($form->node(1)),
        );
    }

    /**
     * Lowers UNLOCK TABLES.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function unlock(Node $tables): UnlockTables
    {
        $this->lowering->options->skip($tables);

        return new UnlockTables();
    }
}
