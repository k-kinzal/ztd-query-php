<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Connection;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\SemanticGraph;

/**
 * Requests detachment of an evaluated SQLite schema name without simulating connection state.
 * @visibility public
 * @example Keeping the requested target
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     (new \SqlSemantics\Statement\Connection\DetachDatabase($scope, new \SqlSemantics\Statement\Expression\SqliteText(new \SqlSemantics\Statement\Literal\StringLiteral('extra'))))->toString() // => "DETACH DATABASE 'extra'"
 */
final class DetachDatabase implements Operation
{
    /**
     * The schema name is an expression in an independent scope.
     */
    public function __construct(public readonly Scope $scope, public readonly ScalarExpression $schema)
    {
        assert($scope->tables === [], 'A detach target has no relation inputs.');
        assert((new SemanticGraph())->containsOnlyValues($schema), 'The target contains only semantic values.');
        foreach ($schema->references() as $reference) {
            assert($reference->scope === $scope, 'Detach references use the request expression scope.');
        }
    }

    /**
     * Writes the requested schema expression without consulting live attachments.
     */
    public function toString(): string
    {
        return 'DETACH DATABASE ' . $this->schema->toString();
    }
}
