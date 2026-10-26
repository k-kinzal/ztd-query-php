<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use Fuzz\Fixture\Tables;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Discovers session-owned variables independently of the SQL implementation under test.
 *
 * The 5.7+ Performance Schema supplies names and unconverted value bytes. Reading
 * those known names on their owning connection also exposes result types, character
 * sets, collations and coercibility: the bytes alone cannot distinguish 1 from '1'.
 * The administrator discovers names even when the tested account loses catalog access.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-user-variable-tables.html.
 */
final class UserVariables
{
    /**
     * Captures values without executing stored SQL or inventing variable names.
     *
     * Probes use the text protocol. Only character_set_client changes temporarily,
     * so catalog names can be spelled in UTF-8 independently of the tested input
     * encoding; value bytes and the connection/result collations remain untouched.
     * Primary results, conditions and session settings must be captured first.
     *
     * @phpstan-impure
     * @return array{raw: array<string, mixed>, comparable: array<string, mixed>}
     * @throws RuntimeException When the owner, inventory or any known variable cannot be read
     */
    public function capture(PDO $connection, PDO $administrator): array
    {
        $emulate = $connection->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $client = null;
        try {
            $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $statement = $connection->query('SELECT CONNECTION_ID(), @@character_set_client');
            $owner = $statement === false ? false : $statement->fetch(PDO::FETCH_NUM);
            if (!is_array($owner) || !is_int($owner[0] ?? null) || $owner[0] < 1 || !is_string($owner[1] ?? null)) {
                throw new RuntimeException('Cannot identify the observed user-variable owner.');
            }
            $inventory = $this->inventory($administrator, $owner[0]);
            $client = $owner[1];
            (new Tables())->execute($connection, "SET SESSION character_set_client = 'utf8mb4'");
            $values = $this->values($connection, $inventory);
            $raw = ['inventory' => $inventory, 'values' => $values];
            $comparable = $raw;
            $comparable['inventory'] = (new Observer())->bag($inventory);
            ksort($comparable['values']);
            return ['raw' => $raw, 'comparable' => $comparable];
        } catch (PDOException $failure) {
            throw new RuntimeException('Cannot read observed user variables.', 0, $failure);
        } finally {
            try {
                if ($client !== null) {
                    (new Tables())->execute($connection, 'SET SESSION character_set_client = ' . $connection->quote($client));
                }
            } finally {
                $connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
        }
    }

    /**
     * Requires exactly one live owning thread before interpreting an empty inventory.
     *
     * @phpstan-impure
     * @return list<array{string, string|null}> Names in system UTF-8 and unconverted stored values
     * @throws RuntimeException When the thread or variable inventory cannot be read
     */
    public function inventory(PDO $administrator, int $connection): array
    {
        $threads = $administrator->query('SELECT THREAD_ID FROM performance_schema.threads WHERE PROCESSLIST_ID = ' . $connection);
        $owners = $threads === false ? [] : $threads->fetchAll(PDO::FETCH_COLUMN);
        if (count($owners) !== 1 || !is_int($owners[0]) || $owners[0] < 1) {
            throw new RuntimeException('Cannot identify exactly one observed user-variable thread.');
        }
        $statement = $administrator->query('SELECT CAST(VARIABLE_NAME AS BINARY), VARIABLE_VALUE FROM performance_schema.user_variables_by_thread WHERE THREAD_ID = ' . $owners[0]);
        if ($statement === false) {
            throw new RuntimeException('Cannot read the observed user-variable inventory.');
        }
        $rows = [];
        $seen = [];
        foreach ($statement->fetchAll(PDO::FETCH_NUM) as $row) {
            if (!is_array($row) || count($row) !== 2 || !is_string($row[0]) || (!is_string($row[1]) && $row[1] !== null) || isset($seen[$row[0]])) {
                throw new RuntimeException('The observed user-variable inventory is malformed.');
            }
            $seen[$row[0]] = true;
            $rows[] = [$row[0], $row[1]];
        }
        return $rows;
    }

    /**
     * Reads only inventoried names, preserving metadata and NULL as well as value bytes.
     *
     * @phpstan-impure
     * @param list<array{string, string|null}> $inventory The owner's independently discovered variables
     * @return array<array-key, array<string, mixed>> Complete observations by variable name
     * @throws RuntimeException When a known variable cannot be read
     */
    public function values(PDO $connection, array $inventory): array
    {
        $observer = new Observer();
        $values = [];
        foreach ($inventory as [$name]) {
            $variable = '@' . $observer->identifier($name);
            $value = $observer->observe($connection, 'SELECT ' . $variable . ' AS `value`, CHARSET(' . $variable . ') AS `character_set`, COLLATION(' . $variable . ') AS `collation_name`, COERCIBILITY(' . $variable . ') AS `coercibility`', true);
            if (isset($value['error'])) {
                throw new RuntimeException('Cannot read observed user variable ' . bin2hex($name) . ': ' . json_encode($value['error']));
            }
            $values[$name] = $value;
        }
        return $values;
    }
}
