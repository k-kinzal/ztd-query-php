<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlSemantics\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Semantics;

/**
 * Measures the SQL binding and complete IR construction: analysis, resolution against declarations, and script splitting.
 */
final class SemanticsBench
{
    private Semantics $semantics;

    private AnalysisContext $context;

    /**
     * Loads the parser and the grammar artifacts before measurement.
     */
    public function setUp(): void
    {
        $this->semantics = new Semantics();
        $this->context = $this->semantics->context([
            'CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)',
        ]);
    }

    /**
     * Analyzes a scalar projection on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchAnalyze(): void
    {
        $this->semantics->analyze('SELECT (2 + 3) * 4 AS score');
    }

    /**
     * Resolves a self join against a complete declaration context on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchResolve(): void
    {
        $this->semantics->analyze('SELECT child.id, parent.score FROM users AS child LEFT JOIN users AS parent ON child.parent_id = parent.id WHERE child.score > 0', $this->context);
    }

    /**
     * Reads a declaration with constraints on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchDeclaration(): void
    {
        $this->semantics->context(['CREATE TABLE items (id INTEGER, amount REAL)']);
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
}
