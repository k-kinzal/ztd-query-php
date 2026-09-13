<?php

declare(strict_types=1);

namespace Fuzz\Robustness;

use Error;
use Fuzz\Correctness\PhysicalTableSnapshot;
use PDO;
use PDOException;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\SqlParseException;
use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Executes grammar-generated SQL through the public adapter and checks physical isolation.
 * Name resolution and supported domain rejections are expected for arbitrary grammar output.
 * Unexpected driver errors and PHP Errors remain findings with the exact input attached.
 */
final class ExecutionCheck
{
    /**
     * Retain the native connection used to inspect physical rows.
     */
    public function __construct(private readonly PDO $native)
    {
    }

    /**
     * Execute generated SQL and compare the physical tables afterward.
     *
     * @throws Error When the adapter leaks an unexpected error or modifies physical rows.
     */
    public function verify(string $sql, string $input): void
    {
        $snapshots = [];
        foreach (['users', 'orders', 'order_items', 'products'] as $table) {
            $snapshots[$table] = PhysicalTableSnapshot::capture($this->native, $table);
        }
        $pdo = ZtdPdo::fromPdo($this->native, new ZtdConfig(UnsupportedSqlBehavior::Exception, UnknownSchemaBehavior::Exception));
        try {
            $pdo->query($sql);
        } catch (PDOException $failure) {
            $domainErrors = [UnsupportedSqlException::class, UnknownSchemaException::class, SchemaNotFoundException::class, ColumnNotFoundException::class, TableAlreadyExistsException::class, ColumnAlreadyExistsException::class, DuplicateKeyException::class, ForeignKeyViolationException::class, NotNullViolationException::class, SqlParseException::class];
            for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
                if ($cause instanceof PDOException && in_array($cause->errorInfo[1] ?? null, [1040, 2002, 2006, 2013], true)) {
                    fwrite(STDERR, 'MySQL connection failed: ' . $cause->getMessage() . PHP_EOL);
                    exit(2);
                }
                foreach ($domainErrors as $domainError) {
                    if ($cause instanceof $domainError) {
                        return;
                    }
                }
            }
            $allowed = [
                ['42S22', 1054], // Grammar-generated column names need not exist.
                ['42S02', 1146], // Grammar-generated table names need not exist.
                ['42S02', 1109], // Multi-table references may name an absent table.
                ['42000', 1327], // Grammar-generated variables are not declared.
            ];
            if (!in_array([$failure->errorInfo[0] ?? null, $failure->errorInfo[1] ?? null], $allowed, true)) {
                throw new Error("Unexpected adapter rejection\nInput: " . bin2hex($input) . "\nSQL: $sql\n" . $failure->getMessage(), 0, $failure);
            }
        } finally {
            try {
                foreach ($snapshots as $table => $snapshot) {
                    PhysicalTableSnapshot::assertUnchanged($this->native, $table, $snapshot, $sql, crc32($input));
                }
            } finally {
                if ($this->native->inTransaction()) {
                    $this->native->rollBack();
                }
            }
        }
    }
}
