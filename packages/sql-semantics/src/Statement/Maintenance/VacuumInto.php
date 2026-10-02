<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Maintenance;

use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\SemanticGraph;

/**
 * Requests a compact SQLite copy at an evaluated destination, leaving its source unchanged.
 * @visibility public
 * @example Keeping the destination as a runtime expression
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     $copy = new \SqlSemantics\Statement\Maintenance\VacuumInto($scope, new \SqlSemantics\Statement\Expression\SqliteText(new \SqlSemantics\Statement\Literal\StringLiteral('copy.db')));
 *     $copy->toString() // => "VACUUM main INTO 'copy.db'"
 */
final class VacuumInto implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Destination expressions do not acquire table visibility from schema declarations.
     */
    public function __construct(public readonly Scope $scope, public readonly ScalarExpression $destination, public readonly Name $schema = new Name('main'))
    {
        \SqlSemantics\Statement\Validation\Check::input($scope->tables === [], 'A vacuum destination has no relation inputs.');
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($destination), 'The destination contains only semantic values.');
        \SqlSemantics\Statement\Validation\Check::input((new Ownership())->accepts($destination, $scope), 'Destination references belong to the vacuum expression scope.');
    }

    /**
     * SQLite ignores a request targeting temp, including any destination expression.
     */
    public function isNoOp(): bool
    {
        return strcasecmp($this->schema->value, 'temp') === 0;
    }

    /**
     * Writes the copying request independently of any original SQL text.
     */
    public function toString(): string
    {
        return 'VACUUM ' . $this->schema->toString() . ' INTO ' . $this->destination->toString();
    }
}
