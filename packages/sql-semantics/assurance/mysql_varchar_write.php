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
        $result += mysqlFixtureDiagnostics($pdo);
        $result['after'] = $snapshot($pdo);
    } finally {
        $pdo->rollBack();
    }

    return [...$result, 'before' => $before, 'restored' => $snapshot($pdo)];
}

/**
 * Collects the last statement's conditions without preparing a new statement.
 */
function mysqlFixtureDiagnostics(PDO $pdo): array
{
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    try {
        return [
            'diagnostics' => $pdo->query('SHOW WARNINGS')->fetchAll(PDO::FETCH_NUM),
            'warningCount' => (int) $pdo->query('SHOW COUNT(*) WARNINGS')->fetchColumn(),
        ];
    } finally {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
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
