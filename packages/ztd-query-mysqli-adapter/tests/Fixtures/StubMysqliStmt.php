<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use Override;
use ReturnTypeWillChange;

/**
 * Test double for mysqli_stmt that allows configuring behavior without PHPUnit mocks.
 *
 * Since the custom PHPStan rule requires createMock() to target interfaces only,
 * this stub provides a concrete test double for mysqli_stmt delegation tests.
 *
 * Initializes a native statement so inherited C extension properties remain readable.
 */
class StubMysqliStmt extends mysqli_stmt
{
    /**
     * Native calls and arguments observed by this test double.
     *
     * @var list<array{string, array<int, mixed>}>
     */
    public array $calls = [];
    /**
     * Create an inert native statement for delegation tests.
     */
    public function __construct()
    {
        parent::__construct(new mysqli(...MySqlContainer::connectionParameters()), 'SELECT 1');
    }

    /**
     * Configured execute return for the native delegation test.
     */
    public bool $executeReturn = true;

    /**
     * @var array<mixed, mixed>|null
     */
    public ?array $executeCalledWithParams = null;

    /**
     * Configured execute call count for the native delegation test.
     */
    public int $executeCallCount = 0;

    /**
     * Configured execute never expected for the native delegation test.
     */
    public bool $executeNeverExpected = false;

    /**
     * Configured get result return for the native delegation test.
     */
    public mysqli_result|false $getResultReturn = false;

    /**
     * Configured num rows return for the native delegation test.
     */
    public int $numRowsReturn = 0;

    /**
     * Configured fetch return for the native delegation test.
     */
    public ?bool $fetchReturn = null;

    /**
     * Configured close called for the native delegation test.
     */
    public bool $closeCalled = false;

    /**
     * Configured reset return for the native delegation test.
     */
    public bool $resetReturn = true;

    /**
     * Configured store result return for the native delegation test.
     */
    public bool $storeResultReturn = true;

    /**
     * @var int|string
     */
    public int|string $affectedRowsValue = 0;

    /**
     * Create an inert native statement for testing delegation.
     */
    public static function create(): self
    {
        $instance = new self();

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
     * {@inheritDoc}
     */
    #[Override]
    public function get_result(): mysqli_result|false
    {
        return $this->getResultReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function num_rows(): int|string
    {
        $this->calls[] = ['num_rows', func_get_args()];
        return $this->numRowsReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function fetch(): ?bool
    {
        return $this->fetchReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function close()
    {
        $this->closeCalled = true;
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function reset(): bool
    {
        return $this->resetReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function bind_result(mixed &...$vars): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function store_result(): bool
    {
        $this->calls[] = ['store_result', func_get_args()];
        return $this->storeResultReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function free_result(): void
    {
        $this->calls[] = ['free_result', func_get_args()];
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function data_seek(int $offset): void
    {
        $this->calls[] = ['data_seek', func_get_args()];
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function result_metadata(): mysqli_result|false
    {
        $this->calls[] = ['result_metadata', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attr_get(int $attribute): int
    {
        $this->calls[] = ['attr_get', func_get_args()];
        return 0;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attr_set(int $attribute, int $value): bool
    {
        $this->calls[] = ['attr_set', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function prepare(string $query): bool
    {
        $this->calls[] = ['prepare', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function send_long_data(int $param_num, string $data): bool
    {
        $this->calls[] = ['send_long_data', func_get_args()];
        return true;
    }

    /**
     * Expose the configured native property value.
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
     * Report whether a supported native property exists.
     */
    public function __isset(string $name): bool
    {
        return in_array($name, ['affected_rows', 'insert_id', 'errno'], true);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_warnings(): mysqli_warning|false
    {
        $this->calls[] = ['get_warnings', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function more_results(): bool
    {
        $this->calls[] = ['more_results', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function next_result(): bool
    {
        $this->calls[] = ['next_result', func_get_args()];
        return false;
    }
}
