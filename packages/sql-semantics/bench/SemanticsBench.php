<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use SqlSemantics\Statement\Traversal;

/**
 * Measures the public SQL-to-statement pipeline, script splitting, and state reading.
 */
final class SemanticsBench
{
    private Semantics $semantics;
    private Schema $schema;

    /**
     * Loads parser and model resources before measurement.
     */
    public function setUp(): void
    {
        $this->semantics = new Semantics(PostgreSqlDialect::PostgreSql);
        $this->schema = new Schema(PostgreSqlDialect::PostgreSql);
        $this->semantics->analyze('SELECT 1');
        $this->schema->analyze('CREATE TABLE warm (id INTEGER)');
    }

    /**
     * Parses and lowers a self join on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchAnalyze(): void
    {
        $this->semantics->analyze('SELECT child.id, parent.score, COALESCE(parent.score, 0) AS effective_score FROM users child LEFT JOIN users parent ON child.parent_id=parent.id WHERE child.score>0 ORDER BY child.id LIMIT 10');
    }

    /**
     * Walks and rewrites every value of a statement on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchRewrite(): void
    {
        $statement = $this->semantics->analyze('SELECT child.id, parent.score FROM users child LEFT JOIN users parent ON child.parent_id=parent.id WHERE child.score>0');
        Traversal::rewrite($statement->command, static fn (\SqlSemantics\Statement\Element $value): \SqlSemantics\Statement\Element => $value);
    }

    /**
     * Finds the boundaries of a three-statement script on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchSplit(): void
    {
        $this->semantics->split('SELECT 1; UPDATE users SET score = 0 WHERE id = 1; DELETE FROM users WHERE id = 2');
    }

    /**
     * Reads a declaration into state on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchSchema(): void
    {
        $this->schema->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
    }
}
