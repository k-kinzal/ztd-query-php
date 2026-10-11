<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;
use PDOException;

/**
 * Observes when a buffered or streaming PDO client receives rows and a query error.
 */
final class PartialResultObservation
{
    /**
     * @return array<string, mixed>
     */
    public static function observe(string $dsn, string $user, string $password, string $sql, bool $buffered, bool $prepared): array
    {
        $attribute = class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
        $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => !$prepared, $attribute => $buffered]);
        $result = ['stage' => $prepared ? 'prepare' : 'query', 'rows' => []];
        try {
            if ($prepared) {
                $statement = $pdo->prepare($sql);
                $result['stage'] = 'execute';
                if ($statement !== false) {
                    $statement->execute();
                }
            } else {
                $statement = $pdo->query($sql);
            }
            $result['stage'] = 'fetch';
            if ($statement !== false) {
                $result['columns'] = $statement->columnCount();
                while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
                    $result['rows'][] = $row;
                }
            }
        } catch (PDOException $error) {
            $result['error'] = $error->errorInfo;
        }

        return $result;
    }
}
