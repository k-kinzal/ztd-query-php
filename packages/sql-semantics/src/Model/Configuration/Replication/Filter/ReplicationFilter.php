<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Filter;

/**
 * One rule of CHANGE REPLICATION FILTER with its complete value list; an empty list clears the rule.
 * @visibility public
 * @example Reading the rule of a filter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (sales)");
 *     $statement->filters[0]->rule()->value // => 'REPLICATE_DO_DB'
 */
interface ReplicationFilter
{
    /**
     * Names the rule the filter sets.
     */
    public function rule(): FilterRule;
}
