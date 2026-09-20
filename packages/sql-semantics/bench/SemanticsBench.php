<?php

declare(strict_types=1);

namespace Bench;

use PhpBench\Attributes as Benchmark;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Analyzer;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Catalog;

/**
 * Measures semantic binding separately from parsing and schema construction.
 */
final class SemanticsBench
{
    private Analyzer $analyzer;

    private Catalog $catalog;

    private Node $query;

    /**
     * Parses the immutable inputs before measurement.
     */
    public function setUp(): void
    {
        $parser = new PostgreSqlParser();
        $this->analyzer = new Analyzer(Dialect::PostgreSql);
        $this->catalog = $this->analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)'));
        $this->query = $parser->parse('SELECT child.id, parent.score, COALESCE(parent.score, 0) AS effective_score FROM users child LEFT JOIN users parent ON child.parent_id=parent.id WHERE child.score>0 ORDER BY child.id LIMIT 10');
    }

    /**
     * Binds and annotates a self join against a reusable catalog.
     */
    #[Benchmark\BeforeMethods('setUp')]
    #[Benchmark\Revs(100)]
    #[Benchmark\Iterations(5)]
    public function benchAnalyze(): void
    {
        $this->analyzer->analyze($this->query, $this->catalog);
    }
}
