<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Storage\Heap;

/**
 * An XA transaction branch published by XA PREPARE, with its retained owner if any.
 *
 * With xa_detach_on_prepare ON the session may start other work, and any session can finish
 * the branch. Otherwise only its owner can finish it until disconnection. MySQL 5.6 discards
 * the branch on disconnection; later releases retain it for recovery. Its row changes stay
 * invisible until XA COMMIT.
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
     * @param int|null $owner The connected session retaining the branch, or null when detached
     * @param list<array{StoredTable, Heap, int, list<int|float|string|null>|null}> $changes Each row the branch changed: its table, the rows of the table, its number and its version after the changes, null when deleted
     */
    public function __construct(public readonly int $format, public readonly string $transaction, public readonly string $branch, public readonly array $changes = [], public readonly ?int $owner = null)
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
