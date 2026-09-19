<?php

declare(strict_types=1);

namespace Fuzz\Correctness;

use Fuzz\Correctness\Postgres\PgCorrectnessHarness;
use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;
use PDO;
use PDOException;

/**
 * Executes byte-driven programs against independent native and simulated database state.
 */
final class SequenceTarget
{
    /**
     * Reuse connection settings while resetting every scenario's catalog and shadow state.
     */
    public function __construct(private readonly CorrectnessHarness|PgCorrectnessHarness|SqliteCorrectnessHarness $harness)
    {
    }

    /**
     * Compare every result, mutation, rejected statement and subsequent read.
     *
     * @throws OracleViolation When a program diverges or changes the physical backing database.
     */
    public function __invoke(string $input): void
    {
        $schema = new SchemaDefinition('fuzz_rows', 'CREATE TABLE fuzz_rows (id INTEGER PRIMARY KEY, quantity INTEGER NOT NULL, name VARCHAR(100))', ['id', 'quantity', 'name'], ['id']);
        try {
            $this->harness->setup($schema, 42);
            $native = $this->harness->getRawPdo();
            $ztd = $this->harness->getZtdPdo();
            foreach ((new SequenceInput())->commands($input) as $index => $command) {
                $before = self::rows($ztd);
                $rawError = null;
                $ztdError = null;
                $rawRows = [];
                $ztdRows = [];
                try {
                    $rawRows = self::execute($native, $command->sql, $command->params, $command->read);
                } catch (PDOException $error) {
                    $rawError = $error;
                }
                try {
                    $ztdRows = self::execute($ztd, $command->sql, $command->params, $command->read);
                } catch (PDOException $error) {
                    $ztdError = $error;
                }
                $context = 'Input: ' . bin2hex($input) . "\nStep: $index\nSQL: {$command->sql}";
                if (($rawError === null) !== ($ztdError === null)) {
                    throw new OracleViolation("Native/ZTD success mismatch\n$context", 0, $ztdError ?? $rawError);
                }
                if ($rawError !== null && $ztdError !== null) {
                    FailureComparison::verify($rawError, $ztdError, $command->sql);
                    self::compare($before, self::rows($ztd), "Failed statement changed shadow state\n$context");
                    self::compare($before, self::rows($native), "Failed statement changed native state\n$context");
                } else {
                    self::compare($rawRows, $ztdRows, "Result mismatch\n$context");
                }
                self::compare(self::rows($native), self::rows($ztd), "Table state mismatch\n$context");
            }
        } finally {
            $this->harness->teardown();
        }
    }

    /**
     * @param list<int|string|null> $params
     * @return list<array<string, string|null>>
     * @throws OracleViolation When preparation or execution fails silently.
     * @throws PDOException When the database rejects the statement.
     */
    public static function execute(PDO $connection, string $sql, array $params, bool $read): array
    {
        $statement = $connection->prepare($sql);
        if ($statement === false || !$statement->execute($params)) {
            throw new OracleViolation('A strict connection failed without an exception: ' . $sql);
        }
        if (!$read) {
            return [['affected' => (string) $statement->rowCount()]];
        }

        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new OracleViolation('Query returned a non-array row.');
            }
            $values = [];
            foreach ($row as $column => $value) {
                if (!is_string($column) || ($value !== null && !is_scalar($value))) {
                    throw new OracleViolation('Query returned an unsupported value.');
                }
                $values[$column] = $value === null ? null : (string) $value;
            }
            $rows[] = $values;
        }

        return $rows;
    }

    /**
     * @return list<array<string, string|null>>
     */
    public static function rows(PDO $connection): array
    {
        return self::execute($connection, 'SELECT id, quantity, name FROM fuzz_rows ORDER BY id', [], true);
    }

    /**
     * @param list<array<string, string|null>> $expected
     * @param list<array<string, string|null>> $actual
     * @throws OracleViolation When values or ordering differ.
     */
    public static function compare(array $expected, array $actual, string $context): void
    {
        if ($expected !== $actual) {
            throw new OracleViolation($context . "\n" . var_export([$expected, $actual], true));
        }
    }
}
