<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Query;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\SemanticGraph;

/**
 * SQLite row-count and skip expressions, evaluated independently of the SELECT input columns.
 * Negative counts mean no upper bound; negative offsets mean zero skipped rows.
 * @visibility public
 * @example Constructing a count limit in its independent expression scope
 *     $scope = new \SqlSemantics\Statement\Relation\Scope(new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main'))));
 *     $count = new \SqlSemantics\Statement\Expression\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('10'));
 *     (new \SqlSemantics\Statement\Query\SqliteLimit($scope, $count))->toString() // => 'LIMIT 10'
 */
final class SqliteLimit
{
    /**
     * Scope and operand relationships are asserted independently of database coercion.
     */
    public function __construct(public readonly Scope $scope, public readonly ScalarExpression $count, public readonly ?ScalarExpression $offset = null, public readonly bool $commaSyntax = false)
    {
        assert($scope->tables === [], 'LIMIT does not resolve names against SELECT input columns.');
        assert(!$commaSyntax || $offset !== null, 'The comma form requires both skip and count expressions.');
        assert((new SemanticGraph())->containsOnlyValues($this), 'Row restrictions retain only immutable semantic values.');
        foreach ([$count, $offset] as $operand) {
            foreach ($operand?->references() ?? [] as $reference) {
                assert($reference->scope === $scope, 'Every row-restriction lookup must use its independent expression scope.');
            }
        }
    }

    /**
     * Changes the upper bound while preserving the offset and its declaration context.
     */
    public function withCount(ScalarExpression $count): self
    {
        return new self($this->scope, $count, $this->offset, $this->commaSyntax);
    }

    /**
     * Reconstructs count and skip roles explicitly, including their reversal in comma notation.
     */
    public function toString(): string
    {
        if ($this->commaSyntax) {
            assert($this->offset !== null, 'Comma notation has both operands.');
            return 'LIMIT ' . $this->offset->toString() . ', ' . $this->count->toString();
        }
        return 'LIMIT ' . $this->count->toString() . ($this->offset === null ? '' : ' OFFSET ' . $this->offset->toString());
    }
}
