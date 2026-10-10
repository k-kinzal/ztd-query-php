<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Storage\Heap;

/**
 * An XA transaction branch that XA PREPARE prepared, which XA COMMIT or XA ROLLBACK ends from any session.
 *
 * The server detaches a prepared branch from its session (xa_detach_on_prepare is ON), so the
 * session may start other work, and the changes of the branch stay invisible until XA COMMIT.
 * The emulator restores the rows the branch changed when it is prepared, releasing their locks,
 * and puts the changed rows back when it is committed; a change another session makes to the same
 * rows meanwhile is lost then.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/xa-states.html,
 * https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_xa_detach_on_prepare.
 *
 * @visibility MySqlMemory
 */
final class PreparedBranch
{
    /**
     * @param int $format The format id of the XID
     * @param string $transaction The bytes of the global transaction id
     * @param string $branch The bytes of the branch qualifier
     * @param list<array{StoredTable, Heap, int, list<int|float|string|null>|null}> $changes Each row the branch changed: its table, the rows of the table, its number and its version after the changes, null when deleted
     */
    public function __construct(public readonly int $format, public readonly string $transaction, public readonly string $branch, public readonly array $changes = [])
    {
    }

    /**
     * Answers the key the server identifies an XID by: its format id, global transaction id and branch qualifier.
     */
    public static function key(int $format, string $transaction, string $branch): string
    {
        return $format . ':' . strlen($transaction) . ':' . $transaction . $branch;
    }
}
