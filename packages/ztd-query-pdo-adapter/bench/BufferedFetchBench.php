<?php

declare(strict_types=1);

namespace Bench;

use PDO;
use PhpBench\Attributes as Bench;
use ZtdQuery\Adapter\Pdo\Driver\BufferedRow;

/**
 * Measures the row shaping used by buffered PDO fetches.
 * Native database preparation, execution, and fixture creation are outside timing.
 */
final class BufferedFetchBench
{
    private BufferedRow $formatter;

    /** @var list<array<string, int|string>> */
    private array $rows;

    /**
     * @var mixed
     */
    public mixed $result = false;

    /**
     * @param array{rows: int, mode: int} $params
     */
    public function setUp(array $params): void
    {
        $this->formatter = new BufferedRow();
        $this->rows = [];
        for ($index = 0; $index < $params['rows']; $index++) {
            $this->rows[] = ['id' => $index, 'name' => 'customer-' . $index, 'status' => 'active', 'balance' => 1234];
        }
    }

    /**
     * @param array{rows: int, mode: int} $params
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\ParamProviders('workloads')]
    #[Bench\Revs(100)]
    public function benchFetch(array $params): void
    {
        foreach ($this->rows as $row) {
            $this->result = $this->formatter->inMode($row, $params['mode']);
        }
    }

    /**
     * @return iterable<string, array{rows: int, mode: int}>
     */
    public function workloads(): iterable
    {
        yield 'assoc-100' => ['rows' => 100, 'mode' => PDO::FETCH_ASSOC];
        yield 'both-100' => ['rows' => 100, 'mode' => PDO::FETCH_BOTH];
        yield 'both-1000' => ['rows' => 1000, 'mode' => PDO::FETCH_BOTH];
    }
}
