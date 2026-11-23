<?php

declare(strict_types=1);

/**
 * Measures one admitted write and snapshots the fixture before the outer rollback.
 */
function mysqlVarcharWrite(PDO $pdo, string $sql, Closure $snapshot): array
{
    $before = $snapshot($pdo);
    $pdo->beginTransaction();
    try {
        $result = executeMySqlVarcharWrite($pdo, $sql);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        try {
            $result['diagnostics'] = $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM);
            $result['warningCount'] = (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn();
        } finally {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        $result['after'] = $snapshot($pdo);
    } finally {
        $pdo->rollBack();
    }

    return [...$result, 'before' => $before, 'restored' => $snapshot($pdo)];
}

function executeMySqlVarcharWrite(PDO $pdo, string $sql): array
{
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $result = ['status' => 'mutation', 'count' => $statement->rowCount(), 'columns' => $statement->columnCount()];
        $statement->closeCursor();

        return $result;
    } catch (PDOException $error) {
        return ['status' => 'db-error', 'state' => $error->errorInfo[0], 'vendorCode' => $error->errorInfo[1]];
    }
}
