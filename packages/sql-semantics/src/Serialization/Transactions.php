<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Transaction as Statement;
use SqlSemantics\Model\Transaction;

/**

 * Writes transaction control without changing the supplied schema or session state. @visibility SqlSemantics

 */
final class Transactions
{
    /**
     * Writes transaction boundaries, savepoint names, and prepared transaction identifiers.
     */
    public static function write(Statement\BeginTransactionStatement|Statement\CommitTransactionStatement|Statement\RollbackTransactionStatement|Statement\SavepointStatement|Statement\ReleaseSavepointStatement|Statement\RollbackToSavepointStatement|Statement\PrepareTransactionStatement|Statement\CommitPreparedStatement|Statement\RollbackPreparedStatement $statement): Tree
    {
        if ($statement instanceof Statement\BeginTransactionStatement) {
            return self::begin($statement);
        }
        if ($statement instanceof Statement\CommitTransactionStatement || $statement instanceof Statement\RollbackTransactionStatement) {
            return new Tree('transaction', [Build::keyword($statement instanceof Statement\CommitTransactionStatement ? 'COMMIT' : 'ROLLBACK'), ...($statement->chaining === Transaction\Chaining::Default ? [] : [Build::keyword($statement->chaining === Transaction\Chaining::Chain ? 'AND CHAIN' : 'AND NO CHAIN')]), ...($statement->release === Transaction\Release::Default ? [] : [Build::keyword($statement->release === Transaction\Release::Release ? 'RELEASE' : 'NO RELEASE')])]);
        }
        if ($statement instanceof Statement\SavepointStatement || $statement instanceof Statement\ReleaseSavepointStatement || $statement instanceof Statement\RollbackToSavepointStatement) {
            $kind = $statement instanceof Statement\SavepointStatement ? 'SAVEPOINT' : ($statement instanceof Statement\ReleaseSavepointStatement ? 'RELEASE SAVEPOINT' : 'ROLLBACK TO SAVEPOINT');
            return new Tree('savepoint', [Build::keyword($kind), Build::identifier([$statement->name], $statement->origin->dialect)]);
        }
        return new Tree('prepared-transaction', [Build::keyword($statement instanceof Statement\PrepareTransactionStatement ? 'PREPARE TRANSACTION' : ($statement instanceof Statement\CommitPreparedStatement ? 'COMMIT PREPARED' : 'ROLLBACK PREPARED')), Expressions::write($statement->transactionId)]);
    }

    /**
     * Writes a transaction start with its optional isolation and access settings.
     */
    public static function begin(Statement\BeginTransactionStatement $statement): Tree
    {
        $characteristics = $statement->characteristics;
        $parts = [...($characteristics->isolation === null ? [] : [Build::keyword('ISOLATION LEVEL ' . $characteristics->isolation->value)]), ...($characteristics->access === null ? [] : [Build::keyword($characteristics->access->value)]), ...($characteristics->deferrable === null ? [] : [Build::keyword($characteristics->deferrable ? 'DEFERRABLE' : 'NOT DEFERRABLE')]), ...($characteristics->consistentSnapshot ? [Build::keyword('WITH CONSISTENT SNAPSHOT')] : [])];
        return new Tree('begin', [Build::keyword($statement->origin->dialect === \SqlSemantics\Dialect::MySql && $parts !== [] ? 'START TRANSACTION' : 'BEGIN'), ...($statement->mode === null ? [] : [Build::keyword($statement->mode->value)]), Build::separated($parts)]);
    }
}
