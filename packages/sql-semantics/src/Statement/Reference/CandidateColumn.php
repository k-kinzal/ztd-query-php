<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference;

use SqlSemantics\Statement\Relation\TableReference;

/**
 * Ownership cannot be settled because at least one relation has no declaration.
 * @visibility public
 * @example Keeping the candidate owner when no declaration is supplied
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $relation = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     (new \SqlSemantics\Statement\Reference\CandidateColumn($relation))->possibilities[0] === $relation // => true
 */
final class CandidateColumn
{
    /**
     * @var non-empty-list<TableReference|ResolvedColumn>
     */
    public readonly array $possibilities;

    /**
     * Includes known matches too: a missing declaration can introduce ambiguity.
     */
    public function __construct(TableReference $first, TableReference|ResolvedColumn ...$rest)
    {
        $this->possibilities = [$first, ...array_values($rest)];
        foreach ($this->possibilities as $possibility) {
            assert(!$possibility instanceof TableReference || (!$possibility->catalog->complete && $possibility->declarations === []), 'A candidate owner must lack a declaration in a partial context.');
        }
    }
}
