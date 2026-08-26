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
    /**
     * @var bool The execute return
     */
    public bool $executeReturn = true;

    /**
     * @var array<mixed, mixed>|null
     */
    public ?array $executeCalledWithParams = null;

    /**
     * @var int The execute call count
     */
    public int $executeCallCount = 0;

    /**
     * @var bool The execute never expected
     */
    public bool $executeNeverExpected = false;

    /**
     * @var mysqli_result|false The get result return
     */
    public mysqli_result|false $getResultReturn = false;

    /**
     * @var int The num rows return
     */
    public int $numRowsReturn = 0;

    /**
     * @var ?bool The fetch return
     */
    public ?bool $fetchReturn = null;

    /**
     * @var bool The close called
     */
    public bool $closeCalled = false;

    /**
     * @var bool The reset return
     */
    public bool $resetReturn = true;

    /**
     * @var bool The store result return
     */
    public bool $storeResultReturn = true;

    /**
     * @var int|string
     */
    public int|string $affectedRowsValue = 0;

    /**
     * Builds.
     *
     * @return self
     */
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

    /**
     * Get_result.
     *
     * @return mysqli_result|false
     */
    #[Override]
    public function get_result(): mysqli_result|false
    {
        return $this->getResultReturn;
    }

    /**
     * Num_rows.
     *
     * @return int|string
     */
    #[Override]
    public function num_rows(): int|string
    {
        return $this->numRowsReturn;
    }

    /**
     * Fetch.
     *
     * @return ?bool
     */
    #[Override]
    public function fetch(): ?bool
    {
        return $this->fetchReturn;
    }

    /**
     * Close.
     *
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function close()
    {
        $this->closeCalled = true;
        return true;
    }

    /**
     * Reset.
     *
     * @return bool
     */
    #[Override]
    public function reset(): bool
    {
        return $this->resetReturn;
    }

    /**
     * Bind_result.
     *
     * @return bool
     */
    #[Override]
    public function bind_result(mixed &...$vars): bool
    {
        return true;
    }

    /**
     * Store_result.
     *
     * @return bool
     */
    #[Override]
    public function store_result(): bool
    {
        return $this->storeResultReturn;
    }

    /**
     * Free_result.
     *
     */
    #[Override]
    public function free_result(): void
    {
    }

    /**
     * Data_seek.
     *
     * @param int $offset
     */
    #[Override]
    public function data_seek(int $offset): void
    {
    }

    /**
     * Result_metadata.
     *
     * @return mysqli_result|false
     */
    #[Override]
    public function result_metadata(): mysqli_result|false
    {
        return false;
    }

    /**
     * Attr_get.
     *
     * @param int $attribute
     * @return int
     */
    #[Override]
    public function attr_get(int $attribute): int
    {
        return 0;
    }

    /**
     * Attr_set.
     *
     * @param int $attribute
     * @param int $value
     * @return bool
     */
    #[Override]
    public function attr_set(int $attribute, int $value): bool
    {
        return true;
    }

    /**
     * Prepare.
     *
     * @param string $query
     * @return bool
     */
    #[Override]
    public function prepare(string $query): bool
    {
        return true;
    }

    /**
     * Send_long_data.
     *
     * @param int $param_num
     * @param string $data
     * @return bool
     */
    #[Override]
    public function send_long_data(int $param_num, string $data): bool
    {
        return true;
    }

    /**
     * __get.
     *
     * @param string $name
     */
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

    /**
     * __isset.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return in_array($name, ['affected_rows', 'insert_id', 'errno'], true);
    }
}
