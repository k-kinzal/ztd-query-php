<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Filter;

/**
 * One (from, to) pair of REPLICATE_REWRITE_DB: events for the first database apply to the second.
 * @visibility public
 * @example Reading a rewrite
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((sales, sales_copy))");
 *     [$statement->filters[0]->rewrites[0]->from, $statement->filters[0]->rewrites[0]->to] // => ['sales', 'sales_copy']
 */
final class DatabaseRewrite
{
    /**
     * Both names are database names as written.
     */
    public function __construct(public readonly string $from, public readonly string $to)
    {
    }
}
