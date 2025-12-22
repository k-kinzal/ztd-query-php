<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli;
use mysqli_result;
use mysqli_stmt;

/**
 * Test double for mysqli that allows configuring behavior without PHPUnit mocks.
 *
 * Since the custom PHPStan rule requires createMock() to target interfaces only,
 * this stub provides a concrete test double for mysqli delegation tests.
 */
class StubMysqli extends mysqli
{
    public mysqli_stmt|false $prepareReturn = false;

    /** @var string|null */
    public ?string $prepareCalledWith = null;

    /** @var mysqli_result|bool */
    public mysqli_result|bool $queryReturn = true;

    /** @var string|null */
    public ?string $queryCalledWith = null;

    public bool $realQueryReturn = true;

    public bool $multiQueryReturn = true;

    public bool $beginTransactionReturn = true;

    public ?int $beginTransactionCalledWithFlags = null;

    public bool $commitReturn = true;

    public ?int $commitCalledWithFlags = null;

    public bool $rollbackReturn = true;

    public ?int $rollbackCalledWithFlags = null;

    public bool $selectDbReturn = true;

    public string $realEscapeStringReturn = '';

    /** @var mysqli_result|bool */
    public mysqli_result|bool $executeQueryReturn = true;

    public bool $closeCalled = false;

    /** @var int|string */
    public int|string $affectedRowsValue = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public function prepare(string $query): mysqli_stmt|false
    {
        $this->prepareCalledWith = $query;
        return $this->prepareReturn;
    }

    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        $this->queryCalledWith = $query;
        return $this->queryReturn;
    }

    public function real_query(string $query): bool
    {
        return $this->realQueryReturn;
    }

    public function multi_query(string $query): bool
    {
        return $this->multiQueryReturn;
    }

    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        $this->beginTransactionCalledWithFlags = $flags;
        return $this->beginTransactionReturn;
    }

    public function commit(int $flags = 0, ?string $name = null): bool
    {
        $this->commitCalledWithFlags = $flags;
        return $this->commitReturn;
    }

    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        $this->rollbackCalledWithFlags = $flags;
        return $this->rollbackReturn;
    }

    #[\ReturnTypeWillChange]
    public function close()
    {
        $this->closeCalled = true;
        return true;
    }

    public function select_db(string $database): bool
    {
        return $this->selectDbReturn;
    }

    public function real_escape_string(string $string): string
    {
        return $this->realEscapeStringReturn;
    }

    public function escape_string(string $string): string
    {
        return $this->realEscapeStringReturn;
    }

    /** @param array<mixed, mixed>|null $params */
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool
    {
        return $this->executeQueryReturn;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'affected_rows') {
            return $this->affectedRowsValue;
        }
        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'affected_rows';
    }
}
