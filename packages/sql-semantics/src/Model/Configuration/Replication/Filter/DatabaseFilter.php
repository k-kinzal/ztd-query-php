<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Filter;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * REPLICATE_DO_DB or REPLICATE_IGNORE_DB with the database names it lists.
 * @visibility public
 * @example Reading the databases
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_IGNORE_DB = (audit, `log`)");
 *     $statement->filters[0]->databases // => ['audit', 'log']
 */
final class DatabaseFilter implements ReplicationFilter
{
    /**
     * @param list<string> $databases Database names in request order; an empty list clears the rule
     * @throws InvalidStructure
     */
    public function __construct(public readonly FilterRule $rule, public readonly array $databases)
    {
        if ($rule !== FilterRule::DoDatabase && $rule !== FilterRule::IgnoreDatabase) {
            throw new InvalidStructure($rule->value . ' does not list databases.');
        }
        Collections::strings($databases);
    }

    /**
     * Names the rule the filter sets.
     */
    public function rule(): FilterRule
    {
        return $this->rule;
    }
}
