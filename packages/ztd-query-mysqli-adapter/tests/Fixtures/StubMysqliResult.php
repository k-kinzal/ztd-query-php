<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli_result;
use Override;
use ReflectionClass;
use ZtdQuery\Connection\StatementInterface;

/**
 * Test double for mysqli_result that allows configuring behavior without PHPUnit mocks.
 *
 * Since the custom PHPStan rule requires createMock() to target interfaces only,
 * this stub provides a concrete test double for mysqli_result delegation tests.
 *
 * Uses a static factory to avoid calling the parent constructor (which requires
 * a connected mysqli instance).
 *
 * @phpstan-import-type Row from StatementInterface
 */
class StubMysqliResult extends mysqli_result
{
    /** @var list<Row> */
    private array $rows = [];

    /** @var list<StubMysqliField> */
    private array $fields = [];

    /**
     * @param list<Row> $rows
     * @param list<StubMysqliField> $fields
     */
    public static function create(array $rows = [], array $fields = []): self
    {
        /** @var self $instance */
        $instance = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->rows = $rows;
        $instance->fields = $fields;

        return $instance;
    }

    /**
     * @return list<Row>
     */
    #[Override]
    public function fetch_all(int $mode = MYSQLI_NUM): array
    {
        return $this->rows;
    }

    /** @return list<StubMysqliField> */
    #[Override]
    public function fetch_fields(): array
    {
        return $this->fields;
    }
}
