<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Filter;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * REPLICATE_DO_TABLE or REPLICATE_IGNORE_TABLE with the database-qualified table names it lists; the tables need not exist.
 * @visibility public
 * @example Reading the tables
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_DO_TABLE = (sales.orders)");
 *     $statement->filters[0]->tables[0]->parts // => ['sales', 'orders']
 */
final class TableFilter implements ReplicationFilter
{
    /**
     * @param list<QualifiedName> $tables Two-part names in request order; an empty list clears the rule
     * @throws InvalidStructure
     */
    public function __construct(public readonly FilterRule $rule, public readonly array $tables)
    {
        if ($rule !== FilterRule::DoTable && $rule !== FilterRule::IgnoreTable) {
            throw new InvalidStructure($rule->value . ' does not list tables.');
        }
        Collections::objects($tables, QualifiedName::class);
        foreach ($tables as $table) {
            if (count($table->parts) !== 2) {
                throw new InvalidStructure('A replication table filter names a database and a table.');
            }
        }
    }

    /**
     * Names the rule the filter sets.
     */
    public function rule(): FilterRule
    {
        return $this->rule;
    }
}
