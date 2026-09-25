<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Grouping;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * CUBE over keys: one grouping set for every subset of the keys.
 * @visibility public
 * @example Reading the keys of a CUBE
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a, b FROM t GROUP BY CUBE(a, b)');
 *     count($statement->groupBy[0]->keys) // => 2
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'SELECT "a" AS "a", "b" AS "b" FROM "public"."t" GROUP BY CUBE("a", "b")'
 */
final class Cube extends GroupingConstruct
{
    /**
     * @var non-empty-list<Expression> Keys whose subsets form the grouping sets; a row value groups its fields together
     */
    public readonly array $keys;

    /**
     * @param list<Expression> $keys
     * @throws InvalidStructure
     */
    public function __construct(array $keys)
    {
        Collections::objects($keys, Expression::class);
        $this->keys = Collections::nonEmpty($keys);
    }
}
