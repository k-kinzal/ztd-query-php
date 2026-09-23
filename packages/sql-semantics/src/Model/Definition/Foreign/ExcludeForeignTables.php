<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Foreign;

use SqlSemantics\Model\Validation\Collections;

/**
 * Imports the remote schema except for the listed relations.
 * @visibility public
 * @example Inspecting a nonempty remote selection
 *     $selection = new \SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables([new \SqlSemantics\Model\Definition\Foreign\ForeignRelation(new \SqlSemantics\Model\Relation\QualifiedName(['users']))]);
 *     $selection->tables[0]->name->parts // => ['users']
 * @example Rejecting an empty explicit selection
 *     new \SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 * @example Rejecting unclassified collection members
 *     new \SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables(['users']); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ExcludeForeignTables
{
    /**
     * @var non-empty-list<ForeignRelation>
     */
    public readonly array $tables;

    /**
     * @param list<ForeignRelation> $tables Ordered remote relation selectors
     */
    public function __construct(array $tables)
    {
        Collections::objects($tables, ForeignRelation::class);
        $this->tables = Collections::nonEmpty($tables);
    }
}
