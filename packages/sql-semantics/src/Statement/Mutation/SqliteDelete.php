<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Mutation;

use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\SemanticGraph;

/**
 * Removes target rows selected by a predicate, without changing the supplied declaration.
 * @visibility public
 * @example Describing deletion independently of stored rows
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $target = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('bar')));
 *     (new \SqlSemantics\Statement\Mutation\SqliteDelete(new \SqlSemantics\Statement\Relation\Scope($catalog, $target)))->toString() // => 'DELETE FROM bar'
 */
final class SqliteDelete implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The actual occurrence targeted for removal.
     */
    public readonly TableReference $target;

    /**
     * The predicate's dependencies belong to the deletion's target scope.
     */
    public function __construct(public readonly Scope $scope, public readonly ?ScalarExpression $where = null)
    {
        \SqlSemantics\Statement\Validation\Check::input(count($scope->tables) === 1, 'Deletion has one target occurrence.');
        $this->target = $scope->tables[0];
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($this), 'Deletion retains only immutable semantic values.');
        \SqlSemantics\Statement\Validation\Check::input($where === null || (new Ownership())->accepts($where, $scope), 'The deletion predicate uses the target scope.');
    }

    /**
     * Reconstructs deletion from its actual target and row predicate.
     */
    public function toString(): string
    {
        return 'DELETE FROM ' . $this->target->toString() . ($this->where === null ? '' : ' WHERE ' . $this->where->toString());
    }
}
