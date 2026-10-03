<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\SemanticGraph;

/**
 * A projected expression with an optional SQL alias and its result column name.
 * @visibility public
 * @example Naming a projected column
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $expression = new \SqlSemantics\Statement\Expression\ColumnReference(new \SqlSemantics\Statement\Relation\Scope($catalog), new \SqlSemantics\Statement\Identifier\Name('id'));
 *     (new \SqlSemantics\Statement\Projection\Field($expression))->name->value // => 'id'
 */
final class Field
{
    /**
     * The label returned by the database, including a server-derived expression label.
     */
    public readonly Name $name;

    /**
     * An unaliased non-column expression needs its dialect's derived output label.
     */
    public function __construct(public readonly ScalarExpression $expression, public readonly ?Name $alias = null, ?Name $derivedName = null, public readonly bool $explicitAlias = true)
    {
        assert((new SemanticGraph())->containsOnlyValues($expression), 'An expression must consist of immutable semantic values.');
        $column = $expression instanceof BooleanReference ? $expression->column : ($expression instanceof ColumnReference ? $expression : null);
        $columnName = $column !== null
            ? ($column->resolution instanceof ResolvedColumn ? $column->resolution->column->name : $column->name)
            : null;
        $name = $alias ?? $columnName ?? $derivedName;
        assert($name !== null, 'An unaliased expression requires its result column label.');
        assert($derivedName === null || $derivedName->value === $name->value, 'A derived name cannot contradict the alias or column name.');
        $this->name = $name;
    }

    /**
     * Reconstructs the projection, including its explicit result alias.
     */
    public function toString(): string
    {
        return $this->expression->toString() . ($this->alias === null ? '' : ($this->explicitAlias ? ' AS ' : ' ') . $this->alias->toString());
    }
}
