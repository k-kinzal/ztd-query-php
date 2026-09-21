<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

/**
 * An ordered result column; duplicate output names remain separate positions.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->outputs[1]->ordinal // => 1
 *
 * @visibility public
 */
final class OutputColumn
{
    /**
     * @param int $ordinal Zero-based result position
     * @param string|null $name Explicit alias or direct column name; null for an engine-generated label
     * @param Expression $expression Typed result expression
     */
    public function __construct(
        public readonly int $ordinal,
        public readonly ?string $name,
        public readonly Expression $expression,
    ) {
    }
}
