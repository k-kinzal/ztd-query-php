<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Filter;

use SqlSemantics\Model\Configuration\Replication\ReplicationText;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * REPLICATE_WILD_DO_TABLE or REPLICATE_WILD_IGNORE_TABLE with its database.table LIKE patterns, kept as written.
 * @visibility public
 * @example Reading the patterns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = ('sales.%')");
 *     $statement->filters[0]->patterns[0]->text // => "'sales.%'"
 */
final class WildTableFilter implements ReplicationFilter
{
    /**
     * @param list<Literal> $patterns Single-line quoted patterns that each contain a dot; an empty list clears the rule
     * @throws InvalidStructure
     */
    public function __construct(public readonly FilterRule $rule, public readonly array $patterns)
    {
        if ($rule !== FilterRule::WildDoTable && $rule !== FilterRule::WildIgnoreTable) {
            throw new InvalidStructure($rule->value . ' does not list table patterns.');
        }
        Collections::objects($patterns, Literal::class);
        foreach ($patterns as $pattern) {
            if (!str_contains(ReplicationText::check($pattern, 'A table pattern', true), '.')) {
                throw new InvalidStructure('A wildcard table filter pattern separates the database and table with a dot.');
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
