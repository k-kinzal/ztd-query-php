<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\QueryKind;

/**
 * Abstract contract test for QueryClassifier / QueryGuard implementations.
 *
 * Although QueryClassifier has no Core interface (each platform uses its own AST types),
 * this contract test enforces behavioral consistency across platforms.
 * See quality-standards.md Section 5.7.
 */
abstract class QueryClassifierContractTest extends TestCase
{
    /**
     * Classify a SQL statement into a QueryKind, or return null if unrecognized.
     */
    abstract protected function classify(string $sql): ?QueryKind;

    abstract protected function selectSql(): string;

    abstract protected function insertSql(): string;

    abstract protected function updateSql(): string;

    abstract protected function deleteSql(): string;

    abstract protected function createTableSql(): string;

    abstract protected function dropTableSql(): string;

    /**
     * SELECT must classify as READ.
     */
    public function testSelectClassifiesAsRead(): void
    {
        $kind = $this->classify($this->selectSql());

        self::assertSame(QueryKind::READ, $kind);
    }

    /**
     * INSERT must classify as WRITE_SIMULATED.
     */
    public function testInsertClassifiesAsWriteSimulated(): void
    {
        $kind = $this->classify($this->insertSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $kind);
    }

    /**
     * UPDATE must classify as WRITE_SIMULATED.
     */
    public function testUpdateClassifiesAsWriteSimulated(): void
    {
        $kind = $this->classify($this->updateSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $kind);
    }

    /**
     * DELETE must classify as WRITE_SIMULATED.
     */
    public function testDeleteClassifiesAsWriteSimulated(): void
    {
        $kind = $this->classify($this->deleteSql());

        self::assertSame(QueryKind::WRITE_SIMULATED, $kind);
    }

    /**
     * CREATE TABLE must classify as DDL_SIMULATED.
     */
    public function testCreateTableClassifiesAsDdlSimulated(): void
    {
        $kind = $this->classify($this->createTableSql());

        self::assertSame(QueryKind::DDL_SIMULATED, $kind);
    }

    /**
     * DROP TABLE must classify as DDL_SIMULATED.
     */
    public function testDropTableClassifiesAsDdlSimulated(): void
    {
        $kind = $this->classify($this->dropTableSql());

        self::assertSame(QueryKind::DDL_SIMULATED, $kind);
    }

    /**
     * Garbage input must return null or throw an exception.
     */
    public function testNullOrExceptionOnGarbageInput(): void
    {
        $exceptionThrown = false;

        try {
            $kind = $this->classify('NOT VALID SQL %%% @@@');
        } catch (\Throwable $e) {
            $exceptionThrown = true;
            $kind = null;
        }

        if (!$exceptionThrown) {
            self::assertNull($kind, 'Garbage input should return null if no exception is thrown');
        } else {
            self::addToAssertionCount(1);
        }
    }
}
