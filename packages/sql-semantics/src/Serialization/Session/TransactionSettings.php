<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Session;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration\Transaction as Statement;
use SqlSemantics\Model\Transaction\Configuration\Locality;
use SqlSemantics\Model\Transaction\Isolation;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes transaction modes and snapshot imports from their specific operation types.
 * @visibility SqlSemantics
 */
final class TransactionSettings
{
    /**
     * Writes the target scope without turning transaction policies into arbitrary variable names.
     */
    public static function write(Statement\SetNextTransactionStatement|Statement\SetDefaultTransactionStatement|Statement\SetCurrentTransactionStatement|Statement\SetSessionTransactionStatement|Statement\SetTransactionSnapshotStatement $statement): Tree
    {
        if ($statement instanceof Statement\SetNextTransactionStatement || $statement instanceof Statement\SetDefaultTransactionStatement) {
            $scope = $statement instanceof Statement\SetDefaultTransactionStatement ? $statement->scope->value . ' ' : '';
            $modes = [...($statement->isolation === null ? [] : [Build::keyword('ISOLATION LEVEL ' . $statement->isolation->value)]), ...($statement->access === null ? [] : [Build::keyword($statement->access->value)])];
            return new Tree('transaction-configuration', [Build::keyword('SET ' . $scope . 'TRANSACTION'), Build::separated($modes)]);
        }
        $set = $statement->locality === Locality::Local ? 'SET LOCAL' : 'SET';
        if ($statement instanceof Statement\SetTransactionSnapshotStatement) {
            return new Tree('snapshot-import', [Build::keyword($set . ' TRANSACTION SNAPSHOT'), Expressions::write($statement->snapshot)]);
        }
        $target = $statement instanceof Statement\SetSessionTransactionStatement ? 'SESSION CHARACTERISTICS AS TRANSACTION' : 'TRANSACTION';
        $modes = array_map(static fn ($mode): Tree => Build::keyword(($mode instanceof Isolation ? 'ISOLATION LEVEL ' : '') . $mode->value), $statement->modes);
        return new Tree('transaction-modes', [Build::keyword($set . ' ' . $target), Build::separated($modes)]);
    }
}
