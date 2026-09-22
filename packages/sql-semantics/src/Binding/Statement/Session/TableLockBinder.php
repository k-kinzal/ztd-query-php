<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Locking\MySqlLockMode;
use SqlSemantics\Model\Locking\MySqlTableLock;
use SqlSemantics\Model\Locking\PostgreSqlLockMode;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Locking\LockRelationsStatement;
use SqlSemantics\Model\Statement\Locking\LockTablesStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds transaction relation locks separately from per-table MySQL session access modes.
 * @visibility SqlSemantics
 */
final class TableLockBinder
{
    /**
     * Routes only productions with explicit table lock semantics.
     */
    public static function bind(Origin $origin, Node $statement, QueryContext $context): ?BoundStatement
    {
        if ($statement->name === 'LockStmt') {
            $tables = array_map(static fn (Node $node): TableReference|OnlyTableReference => TableOccurrence::resolve($node, $context, $origin->scopeId), Tree::outer($statement, ['relation_expr']));
            $mode = Tree::outer($statement, ['lock_type'])[0] ?? null;
            return new LockRelationsStatement($origin, $tables, $mode === null ? PostgreSqlLockMode::AccessExclusive : PostgreSqlLockMode::from(strtoupper(Tree::text($mode))), Tree::child($statement, ['opt_nowait']) !== null);
        }
        if ($statement->name !== 'lock' || Tree::child($statement, ['table_lock_list']) === null) {
            return null;
        }
        $locks = array_map(static fn (Node $node): MySqlTableLock => self::mysql($node, $context, $origin->scopeId), Tree::outer($statement, ['table_lock']));
        return new LockTablesStatement($origin, $locks);
    }

    /**
     * Retains one MySQL table's independent access mode and alias.
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function mysql(Node $node, QueryContext $context, string $scopeId): MySqlTableLock
    {
        $mode = Tree::child($node, ['lock_option']);
        $table = TableOccurrence::resolve($node, $context, $scopeId);
        if (!$table instanceof TableReference || $mode === null) {
            throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('Unclassified MySQL table lock: ' . $node->toString());
        }
        return new MySqlTableLock($table, MySqlLockMode::from(strtoupper(Tree::text($mode))));
    }
}
