<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli_result;
use Override;
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
    /**
     * @var bool Whether the result was let go
     */
    public bool $freed = false;

    /** @var list<Row> */
    private array $rows = [];

    /** @var list<StubMysqliField> */
    private array $fields = [];

    /**
     * Builds a result with no connection behind it.
     *
     * mysqli_result's own constructor wants a connected mysqli, and a test
     * double has none; nothing here reaches the parent, so nothing here needs
     * one.
     *
     * @param list<Row> $rows The rows the result answers
     * @param list<StubMysqliField> $fields The fields the result describes
     */
    public function __construct(array $rows = [], array $fields = [])
    {
        $this->rows = $rows;
        $this->fields = $fields;
    }

    /**
     * Builds a result with no connection behind it.
     *
     * @param list<Row> $rows The rows the result answers
     * @param list<StubMysqliField> $fields The fields the result describes
     *
     * @return self The result
     */
    public static function create(array $rows = [], array $fields = []): self
    {
        return new self($rows, $fields);
    }

    /**
     * @return list<Row>
     */
    #[Override]
    public function fetch_all(int $mode = MYSQLI_NUM): array
    {
        return $this->rows;
    }

    /**
     * @return list<StubMysqliField> The fields
     */
    #[Override]
    public function fetch_fields(): array
    {
        return $this->fields;
    }

    /**
     * Records that the result was let go, without a driver to let go of.
     */
    #[Override]
    public function free(): void
    {
        $this->freed = true;
    }
}
