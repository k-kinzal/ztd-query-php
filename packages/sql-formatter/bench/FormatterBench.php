<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlFormatter\Formatter;
use SqlParser\Sqlite\SqliteParser;

/**
 * Measures parsing, CST layout, and output verification with a reusable parser.
 */
final class FormatterBench
{
    private Formatter $formatter;

    /**
     * Loads parser tables before measurement.
     */
    public function setUp(): void
    {
        $this->formatter = new Formatter(new SqliteParser());
    }

    /**
     * Formats a CTE, join, aggregate, and ordering through the public API.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchFormat(): void
    {
        $this->formatter->format('WITH q AS (SELECT id, score FROM users WHERE score>0) SELECT q.id, count(*) AS n FROM q JOIN events e ON e.user_id=q.id GROUP BY q.id HAVING count(*)>1 ORDER BY q.id;');
    }
}
