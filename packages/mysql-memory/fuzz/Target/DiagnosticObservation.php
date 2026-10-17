<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;
use PDOException;

/**
 * Captures the error and diagnostic totals after a stored program fails.
 */
final class DiagnosticObservation
{
    /**
     * @return array<string, mixed>
     */
    public static function observe(string $dsn, string $user, string $password, string $handler, string $body, int $limit): array
    {
        $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS diagnostic_totals_test');
        $pdo->exec('USE diagnostic_totals_test');
        try {
            $pdo->exec('DROP PROCEDURE IF EXISTS p');
            $pdo->exec('SET max_error_count=' . $limit);
            $pdo->exec('CREATE PROCEDURE p() BEGIN ' . ($handler === '' ? '' : 'DECLARE EXIT HANDLER FOR SQLEXCEPTION ' . $handler . '; ') . $body . '; END');
            $result = [];
            try {
                $statement = $pdo->query('CALL p()');
                if ($statement !== false) {
                    do {
                        $statement->fetchAll(PDO::FETCH_NUM);
                    } while ($statement->nextRowset());
                }
            } catch (PDOException $error) {
                $result['error'] = $error->errorInfo;
            }
            foreach (['warnings' => 'SHOW WARNINGS', 'raised' => 'SHOW COUNT(*) WARNINGS', 'errors' => 'SHOW COUNT(*) ERRORS'] as $name => $sql) {
                $statement = $pdo->query($sql);
                $result[$name] = $statement === false ? false : $statement->fetchAll(PDO::FETCH_NUM);
            }

            return $result;
        } finally {
            $pdo->exec('DROP DATABASE diagnostic_totals_test');
        }
    }
}
