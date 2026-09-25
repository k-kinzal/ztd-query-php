<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Grouping;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * GROUPING SETS: the union of the grouping sets its elements form; an element is a key, a nested ROLLUP, CUBE or
 * GROUPING SETS, or the empty grouping set.
 * @visibility public
 * @example Reading the elements of GROUPING SETS
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a, b FROM t GROUP BY GROUPING SETS (a, (a, b), ())');
 *     count($statement->groupBy[0]->sets) // => 3
 *     $statement->groupBy[0]->sets[2] instanceof \SqlSemantics\Model\Query\Grouping\EmptyGroupingSet // => true
 */
final class GroupingSets extends GroupingConstruct
{
    /**
     * @var non-empty-list<Expression|GroupingConstruct> Elements in written order
     */
    public readonly array $sets;

    /**
     * @param list<Expression|GroupingConstruct> $sets
     * @throws InvalidStructure
     */
    public function __construct(array $sets)
    {
        Collections::alternatives($sets, [Expression::class, GroupingConstruct::class]);
        $this->sets = Collections::nonEmpty($sets);
    }
}
