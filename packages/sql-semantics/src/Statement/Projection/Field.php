<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Projection;

use SqlSemantics\Statement\Expression\BooleanReference;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\Quote;
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
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The label returned by the database, including a server-derived expression label.
     */
    public readonly Name $name;

    /**
     * Derives the output label from the explicit alias, direct column, or rendered expression.
     */
    public function __construct(public readonly ScalarExpression $expression, public readonly ?Name $alias = null, public readonly bool $explicitAlias = true)
    {
        \SqlSemantics\Statement\Validation\Check::input((new SemanticGraph())->containsOnlyValues($expression), 'An expression must consist of immutable semantic values.');
        $base = $expression;
        while ($base instanceof \SqlSemantics\Statement\Expression\Rendering\GroupedExpression) {
            $base = $base->operand;
        }
        $column = $base instanceof BooleanReference && !$base->column->resolution instanceof \SqlSemantics\Statement\Reference\MissingColumn ? $base->column : ($base instanceof ColumnReference ? $base : null);
        $columnName = $column !== null
            ? ($column->resolution instanceof ResolvedColumn ? $column->resolution->column->name : $column->name)
            : null;
        $this->name = $alias ?? $columnName ?? new Name($expression->toString(), Quote::Double);
    }

    /**
     * Reconstructs the projection, including its explicit result alias.
     */
    public function toString(): string
    {
        return $this->expression->toString() . ($this->alias === null ? '' : ($this->explicitAlias ? ' AS ' : ' ') . $this->alias->toString());
    }
}
