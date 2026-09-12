<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli;
use mysqli_result;
use Override;

/**
 * Test double for mysqli_result that allows configuring behavior without PHPUnit mocks.
 *
 * Since the custom PHPStan rule requires createMock() to target interfaces only,
 * this stub provides a concrete test double for mysqli_result delegation tests.
 *
 * Uses a static factory to avoid calling the parent constructor (which requires
 * a connected mysqli instance).
 */
class StubMysqliResult extends mysqli_result
{
    /**
     * Create an inert native result for delegation tests.
     */
    public function __construct()
    {
        parent::__construct(new mysqli(...MySqlContainer::connectionParameters()));
    }

    /**
     * @var array<int, array<string, int|float|string|bool|null>>
     */
    private array $rows = [];

    /**
     * @var list<StubMysqliField>
     */
    private array $fields = [];

    /**
     * @param array<int, array<string, int|float|string|bool|null>> $rows
     * @param list<StubMysqliField> $fields
     */
    public static function create(array $rows = [], array $fields = []): self
    {
        $instance = new self();
        $instance->rows = $rows;
        $instance->fields = $fields;

        return $instance;
    }

    /**
     * @return array<int, array<string, int|float|string|bool|null>>
     */
    #[Override]
    public function fetch_all(int $mode = MYSQLI_NUM): array
    {
        return $this->rows;
    }

    /**
     * @return list<StubMysqliField>
     */
    #[Override]
    public function fetch_fields(): array
    {
        return $this->fields;
    }

    /**
     * Result release observed by the delegation tests.
     */
    public bool $freed = false;

    /**
     * Mark the synthetic result released without using a native result buffer.
     */
    #[Override]
    public function free(): void
    {
        $this->freed = true;
    }
}
