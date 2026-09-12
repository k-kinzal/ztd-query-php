<?php

declare(strict_types=1);

namespace Bench;

use mysqli;
use mysqli_result;
use PhpBench\Attributes as Bench;
use RuntimeException;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Platform\MySql\MySqlResultColumnTypeResolver;

/**
 * Measures metadata adaptation independently of database round trips.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\AfterMethods('tearDown')]
#[Bench\ParamProviders('columns')]
#[Bench\Revs(10000)]
final class ResultMetadataBench
{
    private mysqli $connection;
    private mysqli_result $result;
    private MySqlResultColumnTypeResolver $resolver;

    /**
     * Prepare one native result; setup and cleanup are outside measured work.
     *
     * @param array{columns: int} $params
     * @throws RuntimeException If the native SELECT does not produce metadata.
     */
    public function setUp(array $params): void
    {
        $this->connection = new mysqli(...MySqlContainer::connectionParameters());
        $projection = [];
        for ($column = 0; $column < $params['columns']; $column++) {
            $projection[] = $column . ' AS column_' . $column;
        }
        $result = $this->connection->query('SELECT ' . implode(', ', $projection));
        if (!$result instanceof mysqli_result) {
            throw new RuntimeException('Benchmark SELECT did not return a result.');
        }
        $this->result = $result;
        $this->resolver = new MySqlResultColumnTypeResolver();
    }

    /**
     * Release native resources after timing has finished.
     */
    public function tearDown(): void
    {
        $this->result->free();
        $this->connection->close();
    }

    /**
     * Exercise narrow, ordinary and wide query projections.
     *
     * @return iterable<string, array{columns: int}>
     */
    public function columns(): iterable
    {
        yield 'one-column' => ['columns' => 1];
        yield 'sixteen-columns' => ['columns' => 16];
        yield 'sixty-four-columns' => ['columns' => 64];
    }

    /**
     * Convert field metadata using the same resolver as the production adapter.
     */
    public function benchExtract(): void
    {
        MysqliResultColumnExtractor::extract($this->result, $this->resolver);
    }
}
