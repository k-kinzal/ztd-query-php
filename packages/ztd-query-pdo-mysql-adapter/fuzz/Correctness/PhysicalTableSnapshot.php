<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Error;
use PDO;
use PDOException;

/**
 * Detects physical writes independently of the native-versus-shadow result oracle.
 */
final class PhysicalTableSnapshot
{
    /**
     * @throws Error When the physical table cannot be inspected.
     */
    public static function capture(PDO $pdo, string $table): string
    {
        $quote = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? '`' : '"';
        $relation = $quote . str_replace($quote, $quote . $quote, $table) . $quote;
        try {
            $statement = $pdo->query('SELECT * FROM ' . $relation);
            if ($statement === false) {
                throw new Error('Could not inspect physical table ' . $table);
            }
            $rows = array_map('serialize', $statement->fetchAll(PDO::FETCH_ASSOC));
            sort($rows, SORT_STRING);

            return serialize($rows);
        } catch (PDOException $exception) {
            throw new Error('Could not inspect physical table ' . $table, 0, $exception);
        }
    }

    /**
     * @throws Error When a simulated operation changes the physical database.
     */
    public static function assertUnchanged(PDO $pdo, string $table, string $before, string $sql, int $seed): void
    {
        if ($before !== self::capture($pdo, $table)) {
            throw new Error("ZTD modified a physical table\nSeed: $seed\nTable: $table\nSQL: $sql");
        }
    }
}
