<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

/**
 * SQL operation identities; a concrete statement determines its own identity.
 *
 * @visibility public
 * @example Inspecting an operation
 *     \SqlSemantics\Model\Statement\StatementKind::Insert->value // => 'INSERT'
 */
enum StatementKind: string
{
    case Check = 'CHECK';
    case Checksum = 'CHECKSUM';
    case Repair = 'REPAIR';
    case Optimize = 'OPTIMIZE';
    case XaStart = 'XA START';
    case XaEnd = 'XA END';
    case XaPrepare = 'XA PREPARE';
    case XaCommit = 'XA COMMIT';
    case XaRollback = 'XA ROLLBACK';
    case XaRecover = 'XA RECOVER';
    case Checkpoint = 'CHECKPOINT';
    case Restart = 'RESTART';
    case Shutdown = 'SHUTDOWN';
    case Unlock = 'UNLOCK';
    case Lock = 'LOCK';
    case Listen = 'LISTEN';
    case Unlisten = 'UNLISTEN';
    case Notify = 'NOTIFY';
    case Kill = 'KILL';
    case Install = 'INSTALL';
    case Uninstall = 'UNINSTALL';
    case Clone = 'CLONE';
    case Binlog = 'BINLOG';
    case Discard = 'DISCARD';
    case Declare = 'DECLARE';
    case Fetch = 'FETCH';
    case Move = 'MOVE';
    case Close = 'CLOSE';
    case Select = 'SELECT';
    case Values = 'VALUES';
    case Table = 'TABLE';
    case Union = 'UNION';
    case Intersect = 'INTERSECT';
    case Except = 'EXCEPT';
    case Insert = 'INSERT';
    case Replace = 'REPLACE';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
    case Merge = 'MERGE';
    case Set = 'SET';
    case Reset = 'RESET';
    case Pragma = 'PRAGMA';
    case Create = 'CREATE';
    case Alter = 'ALTER';
    case Drop = 'DROP';
    case Explain = 'EXPLAIN';
    case Prepare = 'PREPARE';
    case Execute = 'EXECUTE';
    case Deallocate = 'DEALLOCATE';
    case Begin = 'BEGIN';
    case Commit = 'COMMIT';
    case Rollback = 'ROLLBACK';
    case Savepoint = 'SAVEPOINT';
    case Release = 'RELEASE';
    case Truncate = 'TRUNCATE';
    case Grant = 'GRANT';
    case Revoke = 'REVOKE';
    case Call = 'CALL';
    case Do = 'DO';
    case Show = 'SHOW';
    case Use = 'USE';
    case Analyze = 'ANALYZE';
    case Vacuum = 'VACUUM';
    case Attach = 'ATTACH';
    case Detach = 'DETACH';
    case Reindex = 'REINDEX';
}
