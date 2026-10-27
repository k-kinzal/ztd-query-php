<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use mysqli;
use MySqlMemory\Server\MemoryDatabase;

/**
 * Executes each generated SQL input once on each database and compares its replies.
 *
 * Connections retain the same input history, so a generated write can be read by
 * later inputs. A failure includes that history for reproduction. No catalog
 * inspection, state restoration or second native execution changes the result.
 */
final class ExecutionTarget
{
    /** @var list<string> */
    private array $history = [];

    /**
     * Counts generated inputs started by the entrypoint.
     */
    public int $attempts = 0;

    /**
     * Counts inputs whose replies matched.
     */
    public int $verified = 0;

    /**
     * Shares the two connections and the MemoryDB internal-failure channel.
     * Both connections use MYSQLI_REPORT_OFF so SQL failures remain reply values.
     */
    public function __construct(private readonly mysqli $native, private readonly mysqli $memory, private readonly MemoryDatabase $server)
    {
    }

    /**
     * Requires identical result sets, metadata, affected rows, errors and warning counts.
     *
     * @throws Error When a reply differs or the driver cannot represent it
     */
    public function verify(string $sql, string $input): void
    {
        $this->history[] = $sql;
        $expected = $this->execute($this->native, $sql);
        $actual = $this->execute($this->memory, $sql);
        $this->server->assertNoInternalFailure();
        if ($expected !== $actual) {
            throw new Error("MySQL execution differs.\nInput (hex): " . bin2hex($input)
                . "\nSQL: {$sql}\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true)
                . "\nSQL history: " . var_export($this->history, true));
        }
        $this->verified++;
    }

    /**
     * Retains partial replies when an error occurs while advancing result sets.
     * Driver errors are SQL outcomes; a MemoryDB implementation failure is checked
     * through the server's independent failure channel after both executions.
     *
     * No auxiliary SQL is executed to collect diagnostics: every value below
     * comes from the reply to the generated input itself.
     *
     * @throws Error When the driver returns an invalid reply structure
     * @return array{results: list<array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null}>, error: array{string, int, string}|null}
     */
    public function execute(mysqli $connection, string $sql): array
    {
        $results = [];
        if (!$connection->multi_query($sql)) {
            return ['results' => [], 'error' => [$connection->sqlstate, $connection->errno, $connection->error]];
        }
        do {
            $result = $connection->store_result();
            if ($result === false && $connection->errno !== 0) {
                return ['results' => $results, 'error' => [$connection->sqlstate, $connection->errno, $connection->error]];
            }
            $columns = [];
            $rows = [];
            if ($result !== false) {
                foreach ($result->fetch_fields() as $column) {
                    $metadata = [];
                    foreach (get_object_vars($column) as $key => $value) {
                        if (!is_string($key)) {
                            throw new Error('mysqli column metadata uses named fields.');
                        }
                        $metadata[$key] = $value;
                    }
                    $columns[] = $metadata;
                }
                foreach ($result->fetch_all(MYSQLI_NUM) as $row) {
                    if (!array_is_list($row)) {
                        throw new Error('mysqli numeric rows preserve column order.');
                    }
                    $rows[] = $row;
                }
            }
            $results[] = [
                'columns' => $columns,
                'rows' => $rows,
                'affected' => $connection->affected_rows,
                'insertId' => $connection->insert_id,
                'warnings' => $connection->warning_count,
                'info' => $connection->info,
            ];
            if ($result !== false) {
                $result->free();
            }
            if (!$connection->more_results()) {
                break;
            }
            if (!$connection->next_result()) {
                return ['results' => $results, 'error' => [$connection->sqlstate, $connection->errno, $connection->error]];
            }
        } while (true);
        return ['results' => $results, 'error' => null];
    }
}
