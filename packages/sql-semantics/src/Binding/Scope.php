<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\TableUse;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

/**
 * A relation namespace and NULL-extension facts at one evaluation stage.
 *
 * @visibility SqlSemantics
 */
final class Scope
{
    /**
     * @param list<TableUse> $relations
     * @param array<string, list<string>> $extensions
     * @param array<string, Expression> $merged USING columns visible without a qualifier
     */
    public function __construct(
        public readonly Identifiers $identifiers,
        public readonly array $relations = [],
        public readonly array $extensions = [],
        public readonly ?self $parent = null,
        public readonly ?QueryContext $queries = null,
        public readonly array $merged = [],
    ) {
    }

    /**
     * @param list<string> $parts
     * @throws SemanticException
     */
    public function column(array $parts, Node|Token $source): Expression
    {
        $name = $parts[count($parts) - 1];
        $qualifiers = array_slice($parts, 0, -1);
        if ($qualifiers === []) {
            foreach ($this->merged as $label => $expression) {
                if ($this->identifiers->equal($label, $name)) {
                    return $expression;
                }
            }
        }
        $matches = [];
        $origins = [];
        foreach ($this->relations as $relation) {
            if (!$this->matches($relation, $qualifiers)) {
                continue;
            }
            foreach ($relation->declaration->columns as $ordinal => $column) {
                if ($this->identifiers->equal($column->name, $name)) {
                    $matches[] = new ColumnBinding($relation->id, $relation->declaration, $column);
                    if ($relation->query !== null && isset($relation->query->outputs[$ordinal])) {
                        $origins[] = $relation->query->outputs[$ordinal]->expression;
                    }
                }
            }
        }
        if ($matches === [] && $this->parent !== null && ($qualifiers === [] || array_filter($this->relations, fn (TableUse $relation): bool => $this->matches($relation, $qualifiers)) === [])) {
            return $this->parent->column($parts, $source);
        }
        if (count($matches) !== 1) {
            throw new SemanticException($matches === [] ? 'unknown-column' : 'ambiguous-column', 'Cannot resolve column unambiguously: ' . implode('.', $parts), $source);
        }
        $binding = $matches[0];
        $extensions = $this->extensions[$binding->relationId] ?? [];

        return new Expression(ExpressionKind::Column, $binding->column->type, $extensions === [] ? $binding->column->nullability : Nullability::MaybeNull, $source, $origins, binding: $binding, nullExtendedBy: $extensions);
    }

    /**
     * @param list<string> $qualifiers
     */
    public function matches(TableUse $relation, array $qualifiers): bool
    {
        if ($qualifiers === []) {
            return true;
        }
        if (count($qualifiers) === 1) {
            return $this->identifiers->relationEqual($qualifiers[0], $relation->alias ?? $relation->declaration->name);
        }

        return count($qualifiers) === 2 && $relation->alias === null
            && $this->identifiers->relationEqual($qualifiers[0], $relation->declaration->schema)
            && $this->identifiers->relationEqual($qualifiers[1], $relation->declaration->name);
    }

    /**
     * @throws SemanticException
     */
    public function combine(self $right, Node $source): self
    {
        foreach ($this->relations as $leftRelation) {
            foreach ($right->relations as $rightRelation) {
                if ($this->identifiers->relationEqual($leftRelation->alias ?? $leftRelation->declaration->name, $rightRelation->alias ?? $rightRelation->declaration->name)) {
                    throw new SemanticException('duplicate-relation', 'Duplicate relation name in scope.', $source);
                }
            }
        }

        return new self($this->identifiers, [...$this->relations, ...$right->relations], $this->extensions + $right->extensions, $this->parent ?? $right->parent, $this->queries ?? $right->queries, $this->merged + $right->merged);
    }

    /**
     * Adds a join to the NULL provenance of every visible relation.
     */
    public function extend(string $joinId): self
    {
        $extensions = $this->extensions;
        foreach ($this->relations as $relation) {
            $extensions[$relation->id] = [...($extensions[$relation->id] ?? []), $joinId];
        }

        $merged = array_map(static fn (Expression $value): Expression => new Expression($value->kind, $value->type, Nullability::MaybeNull, $value->source, $value->operands, $value->binding, $value->symbol, [...$value->nullExtendedBy, $joinId], $value->query), $this->merged);
        return new self($this->identifiers, $this->relations, $extensions, $this->parent, $this->queries, $merged);
    }
    /**
     * @return array<string, Expression> Unqualified joined output columns
     */
    public function outputColumns(Node $source): array
    {
        $columns = $this->merged;
        foreach ($this->relations as $relation) {
            foreach ($relation->declaration->columns as $column) {
                if (!isset($columns[$column->name])) {
                    $columns[$column->name] = $this->column([$relation->alias ?? $relation->declaration->name, $column->name], $source);
                }
            }
        }
        return $columns;
    }

}
