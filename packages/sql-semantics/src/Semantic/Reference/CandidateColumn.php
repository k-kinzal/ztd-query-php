<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Reference;

use SqlSemantics\Semantic\Relation\TableReference;

/**
 * Candidate owners whose declarations were not supplied.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT foo FROM bar');
 *     count($statement->field('foo')->expression->binding->relations) // => 1
 *
 * @visibility public
 */
final class CandidateColumn
{
    /**
     * @var non-empty-list<TableReference>
     */
    public readonly array $relations;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(TableReference $first, TableReference ...$rest)
    {
        $this->relations = [$first, ...array_values($rest)];
        foreach ($this->relations as $relation) {
            assert(!$relation->catalogSupplied, 'Candidate ownership requires an absent catalog.');
        }
    }
}
