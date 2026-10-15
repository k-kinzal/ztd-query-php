<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use mysqli_stmt;
use RuntimeException;

/**
 * Observes native rows and protocol column metadata without depending on memory's types.
 * Source: https://www.php.net/manual/en/mysqli-result.fetch-fields.php.
 */
final class Native
{
    /**
     * @return array{results: list<array<string, mixed>>, error?: array{int, string, string}, warnings: list<list<mixed>>|null, disconnected: bool} All result sets, the terminal error and stored conditions
     * @throws RuntimeException When the driver does not produce an observation
     */
    public function observe(mysqli $connection, string $sql, int $parameters = 0): array
    {
        $observation = ['results' => []];
        $statement = null;
        try {
            if ($parameters > 0) {
                $statement = $connection->prepare($sql);
                if ($statement === false || !$statement->execute(array_fill(0, $parameters, null))) {
                    throw new RuntimeException('Native preparation did not produce an execution.');
                }
            } elseif (!$connection->real_query($sql)) {
                throw new RuntimeException('Native query did not produce an execution.');
            }
            do {
                $result = $statement === null ? $connection->store_result() : $statement->get_result();
                $observation['results'][] = $result instanceof mysqli_result ? $this->result($result) : ['affected' => $connection->affected_rows, 'lastInsertId' => (string) $connection->insert_id];
                $source = $statement ?? $connection;
            } while ($source->more_results() && $source->next_result());
        } catch (mysqli_sql_exception $error) {
            $observation['error'] = [$error->getCode(), $error->getSqlState(), $error->getMessage()];
        } finally {
            if ($statement instanceof mysqli_stmt) {
                $statement->close();
            }
        }
        $observation += $this->conditions($connection);

        return $observation;
    }

    /**
     * Reads conditions, or records that the completed statement disconnected its session.
     * An error during the target itself is still retained by observe().
     *
     * @return array{warnings: list<list<mixed>>|null, disconnected: bool}
     * @throws RuntimeException When a live connection supplies no condition result
     */
    public function conditions(mysqli $connection): array
    {
        try {
            $warnings = $connection->query('SHOW WARNINGS');
        } catch (mysqli_sql_exception $error) {
            if (!in_array($error->getCode(), [2006, 2013], true)) {
                throw $error;
            }

            return ['warnings' => null, 'disconnected' => true];
        }
        if (!$warnings instanceof mysqli_result) {
            throw new RuntimeException('Native conditions could not be read.');
        }
        $rows = array_map(static fn (array $row): array => array_values($row), $warnings->fetch_all(MYSQLI_NUM));
        $warnings->free();

        return ['warnings' => $rows, 'disconnected' => false];
    }

    /**
     * @return array<string, mixed>
     */
    public function result(mysqli_result $result): array
    {
        $columns = [];
        foreach ($result->fetch_fields() as $field) {
            $columns[] = ['name' => $field->name, 'orgname' => $field->orgname, 'table' => $field->table, 'orgtable' => $field->orgtable, 'db' => $field->db, 'charsetnr' => $field->charsetnr, 'length' => $field->length, 'type' => $field->type, 'flags' => $field->flags, 'decimals' => $field->decimals];
        }
        $rows = array_map(fn (array $row): array => array_map($this->value(...), $row), $result->fetch_all(MYSQLI_NUM));
        $result->free();

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Converts binary-protocol numeric values to the text value exposed by direct execution.
     *
     * @throws RuntimeException When the driver supplies a value outside its documented domain
     */
    public function value(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new RuntimeException('Unexpected native result value: ' . get_debug_type($value));
    }
}
