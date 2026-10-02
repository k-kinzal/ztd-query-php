<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Connection;

use SqlSemantics\Statement\Expression\Reference\Ownership;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\SemanticGraph;

/**
 * Requests attachment of a SQLite database under an evaluated schema name.
 * @visibility public
 * @example Describing a request without opening a file
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     $text = static fn (string $value): \SqlSemantics\Statement\Expression\SqliteText => new \SqlSemantics\Statement\Expression\SqliteText(new \SqlSemantics\Statement\Literal\StringLiteral($value));
 *     (new \SqlSemantics\Statement\Connection\AttachDatabase($scope, $text(':memory:'), $text('extra')))->toString() // => "ATTACH DATABASE ':memory:' AS 'extra'"
 */
final class AttachDatabase implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Each operand is independently evaluated; none introduces a table or changes the catalog.
     */
    public function __construct(public readonly Scope $scope, public readonly ScalarExpression $filename, public readonly ScalarExpression $schema, public readonly ?ScalarExpression $key = null)
    {
        \SqlSemantics\Statement\Validation\Check::input($scope->tables === [], 'Attachment expressions have no relation inputs.');
        foreach ([$filename, $schema, ...($key === null ? [] : [$key])] as $expression) {
            \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($expression), 'An attachment operand contains only semantic values.');
            \SqlSemantics\Statement\Validation\Check::input((new Ownership())->accepts($expression, $scope), 'Attachment references use the request expression scope.');
        }
    }

    /**
     * Writes the attachment request and optional key expression.
     */
    public function toString(): string
    {
        return 'ATTACH DATABASE ' . $this->filename->toString() . ' AS ' . $this->schema->toString() . ($this->key === null ? '' : ' KEY ' . $this->key->toString());
    }
}
