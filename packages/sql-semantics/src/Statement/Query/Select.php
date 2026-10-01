<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Query;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use SqlSemantics\Statement\SemanticGraph;

/**
 * A projection over named relation occurrences, with an optional row predicate.
 * @visibility public
 * @example Updating the projection without changing its input declarations
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $table = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('bar')));
 *     $scope = new \SqlSemantics\Statement\Relation\Scope($catalog, $table);
 *     $foo = new \SqlSemantics\Statement\Projection\Field(new \SqlSemantics\Statement\Expression\ColumnReference($scope, new \SqlSemantics\Statement\Identifier\Name('foo')));
 *     $query = new \SqlSemantics\Statement\Query\Select(new \SqlSemantics\Statement\Projection\Fields($scope, $foo));
 *     $query->toString() // => 'SELECT foo FROM bar'
 */
final class Select implements Operation
{
    /**
     * The namespace shared by this query's input references and projection.
     */
    public readonly Scope $scope;

    /**
     * Keeps one source of truth for references and asserts predicate ownership.
     */
    public function __construct(private readonly Fields $projection, public readonly ?ScalarExpression $where = null, public readonly Quantifier $quantifier = Quantifier::Default)
    {
        $this->scope = $projection->scope;
        assert((new SemanticGraph())->containsOnlyValues($this), 'A SELECT retains only immutable semantic values.');
        foreach ($where?->references() ?? [] as $reference) {
            assert($reference->scope === $this->scope, 'Every predicate column must belong to this SELECT scope.');
        }
        foreach ($where === null ? [] : (new SemanticGraph())->conditionalAliases($where) as $reference) {
            assert(($projection->matchingAliases($reference->alias->name->value)[0] ?? null) === $reference->alias->field, 'A conditional alias must keep its original fallback field until the input declaration is known.');
        }
    }

    /**
     * Returns the immutable collection of projected expressions and labels.
     */
    public function fields(): Fields
    {
        return $this->projection;
    }

    /**
     * Returns a uniquely named result position using the query's identifier rules.
     */
    public function field(string $name): Field
    {
        return $this->projection->field($name);
    }

    /**
     * Replaces the projection while preserving the input scope and row predicate.
     */
    public function withFields(Fields $fields): self
    {
        assert($fields->scope === $this->scope, 'Replacing a projection must preserve its input scope.');
        return new self($fields, $this->where, $this->quantifier);
    }

    /**
     * Replaces the predicate and rechecks every reference against the input scope.
     */
    public function withWhere(?ScalarExpression $where): self
    {
        return new self($this->projection, $where, $this->quantifier);
    }

    /**
     * Reconstructs SELECT from its projection, inputs, and row selection.
     */
    public function toString(): string
    {
        $sql = 'SELECT' . ($this->quantifier === Quantifier::Default ? '' : ' ' . $this->quantifier->value) . ($this->projection->items === [] ? '' : ' ' . $this->projection->toString());
        $sql .= $this->scope->tables === [] ? '' : ' FROM ' . implode(', ', array_map(static fn (TableReference $table): string => $table->toString(), $this->scope->tables));
        return $sql . ($this->where === null ? '' : ' WHERE ' . $this->where->toString());
    }
}
