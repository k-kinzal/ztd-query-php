<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Filter;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * REPLICATE_REWRITE_DB with its database rewrites.
 * @visibility public
 * @example Counting the rewrites
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((a, b), (c, d))");
 *     count($statement->filters[0]->rewrites) // => 2
 */
final class RewriteFilter implements ReplicationFilter
{
    /**
     * @param list<DatabaseRewrite> $rewrites Rewrites in request order; an empty list clears the rule
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $rewrites)
    {
        Collections::objects($rewrites, DatabaseRewrite::class);
    }

    /**
     * Names the rule the filter sets.
     */
    public function rule(): FilterRule
    {
        return FilterRule::RewriteDatabase;
    }
}
