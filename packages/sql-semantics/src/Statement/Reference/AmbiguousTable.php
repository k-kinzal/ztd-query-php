<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

use SqlSemantics\Statement\Relation\TableReference;

/**
 * Conflicting context declarations prevent identification of a column's owning table.
 * @visibility public
 * @example Preserving a conflicting declaration context
 *     $table = new \SqlSemantics\Statement\Schema\Table(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), \SqlSemantics\Statement\Identifier\Comparison::Sensitive, \SqlSemantics\Statement\Identifier\Comparison::Sensitive, true, null, null,new \SqlSemantics\Statement\Contract\LanguageProfile(\SqlSemantics\Statement\Contract\GrammarRelease::Sqlite3472), $table, new \SqlSemantics\Statement\Schema\Table($table->name));
 *     $relation = new \SqlSemantics\Statement\Relation\TableReference($catalog, $table->name);
 *     count((new \SqlSemantics\Statement\Reference\AmbiguousTable($relation))->relations[0]->declarations) // => 2
 */
final class AmbiguousTable
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var non-empty-list<TableReference>
     */
    public readonly array $relations;

    /**
     * Keeps every relation occurrence whose declarations conflict.
     */
    public function __construct(TableReference $first, TableReference ...$rest)
    {
        $this->relations = [$first, ...array_values($rest)];
        foreach ($this->relations as $relation) {
            \SqlSemantics\Statement\Validation\Check::input(count($relation->declarations) > 1, 'An ambiguous relation requires conflicting declarations.');
        }
    }
}
