<?php

declare(strict_types=1);

namespace Bench;

use PDO;
use PDOStatement;
use PhpBench\Attributes as Bench;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * Measures the consumer's connection, query and prepared execution paths.
 * Native schema creation and shadow fixture population run outside timing.
 */
final class PdoQueryBench
{
    private PDO $native;

    private ZtdPdo $pdo;

    private PDOStatement $statement;

    /**
     * Populate the shadow and prepare a reusable consumer statement.
     *
     * @param array{rows: int} $params
     * @throws RuntimeException When preparation fails.
     */
    public function setUp(array $params): void
    {
        $this->native = new PDO('sqlite::memory:');
        $this->native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->pdo = ZtdPdo::fromPdo($this->native);
        $rows = [];
        for ($id = 1; $id <= $params['rows']; $id++) {
            $rows[] = "({$id}, 'user-{$id}')";
        }
        $this->pdo->exec('INSERT INTO users VALUES ' . implode(', ', $rows));
        $statement = $this->pdo->prepare('SELECT id, name FROM users WHERE id >= ?');
        if ($statement === false) {
            throw new RuntimeException('Benchmark preparation failed.');
        }
        $this->statement = $statement;
    }

    /**
     * Wrap an existing native connection.
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\ParamProviders('workloads')]
    #[Bench\Revs(1000)]
    public function benchWrapConnection(): void
    {
        ZtdPdo::fromPdo($this->native);
    }

    /**
     * Rewrite and fetch a shadowed query.
     *
     * @throws RuntimeException When the query fails.
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\ParamProviders('workloads')]
    #[Bench\Revs(100)]
    public function benchQuery(): void
    {
        $statement = $this->pdo->query('SELECT id, name FROM users');
        if ($statement === false) {
            throw new RuntimeException('Benchmark query failed.');
        }
        $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a prepared statement against the current shadow.
     */
    #[Bench\BeforeMethods('setUp')]
    #[Bench\ParamProviders('workloads')]
    #[Bench\Revs(100)]
    public function benchPreparedExecution(): void
    {
        $this->statement->execute([1]);
        $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compare single-row and batch workloads.
     *
     * @return iterable<string, array{rows: int}>
     */
    public function workloads(): iterable
    {
        yield 'single-row' => ['rows' => 1];
        yield 'hundred-rows' => ['rows' => 100];
    }
}
