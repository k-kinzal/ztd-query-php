<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * A result ordering expression with explicit direction and NULL placement.
 *
 * @example Reading semantic facts
 *     $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
 *     $analyzer = new \SqlSemantics\Analyzer(\SqlSemantics\Dialect::PostgreSql);
 *     $catalog = $analyzer->schema($parser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)'));
 *     $query = $analyzer->analyze($parser->parse('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC'), $catalog);
 *     $query->orderBy[0]->descending // => true
 *
 * @visibility public
 */
final class Ordering
{
    /**
     * @param Expression $expression Sort expression
     * @param bool $descending Descending order
     * @param bool|null $nullsFirst Explicit NULLS FIRST/LAST; null uses the dialect default
     */
    public function __construct(
        public readonly Expression $expression,
        public readonly bool $descending = false,
        public readonly ?bool $nullsFirst = null,
    ) {
    }
}
