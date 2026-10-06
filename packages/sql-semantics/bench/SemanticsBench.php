<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

/**
 * Measures the public SQL-to-operation pipeline: analysis, resolution against declarations, and script splitting.
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
        $this->semantics = new Semantics(Dialect::Sqlite);
        $this->context = $this->semantics->context([
            $this->semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)'),
        ]);
    }

    /**
     * Analyzes a self join against an open context on each iteration.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchAnalyze(): void
    {
        $this->semantics->analyze('SELECT child.id, parent.score, coalesce(parent.score, 0) AS effective_score FROM users AS child LEFT JOIN users AS parent ON child.parent_id = parent.id WHERE child.score > 0 ORDER BY child.id LIMIT 10');
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
        $this->semantics->analyze('CREATE TABLE items (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES parent(id), amount NUMERIC(7) DEFAULT 1.25)', []);
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
