<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli_result;
use ReflectionClass;

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
    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public static function create(array $rows = []): self
    {
        /** @var self $instance */
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->rows = $rows;

        return $instance;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch_all(int $mode = MYSQLI_NUM): array
    {
        return $this->rows;
    }
}
