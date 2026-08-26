<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli_result;
use mysqli_stmt;
use Override;
use ReflectionClass;
use ReturnTypeWillChange;

/**
 * Test double for mysqli_stmt that allows configuring behavior without PHPUnit mocks.
 *
 * Since the custom PHPStan rule requires createMock() to target interfaces only,
 * this stub provides a concrete test double for mysqli_stmt delegation tests.
 *
 * Uses a static factory to avoid calling the parent constructor (which requires
 * a connected mysqli instance).
 */
class StubMysqliStmt extends mysqli_stmt
{
    public bool $executeReturn = true;

    /**
     * @var array<mixed, mixed>|null
     */
    public ?array $executeCalledWithParams = null;

    public int $executeCallCount = 0;

    public bool $executeNeverExpected = false;

    public mysqli_result|false $getResultReturn = false;

    public int $numRowsReturn = 0;

    public ?bool $fetchReturn = null;

    public bool $closeCalled = false;

    public bool $resetReturn = true;

    public bool $storeResultReturn = true;

    /**
     * @var int|string
     */
    public int|string $affectedRowsValue = 0;

    public static function create(): self
    {
        /** @var self $instance */
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();

        return $instance;
    }

    /**
     * @param array<mixed, mixed>|null $params
     */
    #[Override]
    public function execute(?array $params = null): bool
    {
        $this->executeCallCount++;
        $this->executeCalledWithParams = $params;
        return $this->executeReturn;
    }

    #[Override]
    public function get_result(): mysqli_result|false
    {
        return $this->getResultReturn;
    }

    #[Override]
    public function num_rows(): int|string
    {
        return $this->numRowsReturn;
    }

    #[Override]
    public function fetch(): ?bool
    {
        return $this->fetchReturn;
    }

    #[Override]
    #[ReturnTypeWillChange]
    public function close()
    {
        $this->closeCalled = true;
        return true;
    }

    #[Override]
    public function reset(): bool
    {
        return $this->resetReturn;
    }

    #[Override]
    public function bind_result(mixed &...$vars): bool
    {
        return true;
    }

    #[Override]
    public function store_result(): bool
    {
        return $this->storeResultReturn;
    }

    #[Override]
    public function free_result(): void
    {
    }

    #[Override]
    public function data_seek(int $offset): void
    {
    }

    #[Override]
    public function result_metadata(): mysqli_result|false
    {
        return false;
    }

    #[Override]
    public function attr_get(int $attribute): int
    {
        return 0;
    }

    #[Override]
    public function attr_set(int $attribute, int $value): bool
    {
        return true;
    }

    #[Override]
    public function prepare(string $query): bool
    {
        return true;
    }

    #[Override]
    public function send_long_data(int $param_num, string $data): bool
    {
        return true;
    }

    public function __get(string $name): mixed
    {
        if ($name === 'affected_rows') {
            return $this->affectedRowsValue;
        }
        if ($name === 'insert_id') {
            return 0;
        }
        if ($name === 'errno') {
            return 0;
        }
        return null;
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['affected_rows', 'insert_id', 'errno'], true);
    }
}
