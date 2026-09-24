<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Expression;
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
     * @param array<int|string, Expression> $merged USING columns visible without a qualifier
     * @param list<\SqlSemantics\Model\OutputColumn>|null $outputs Ordered joined row; null for a base relation scope
     * @param bool $detached Whether no table is known, as in the MySQL 5 parser entries, so that unmatched column names stay unresolved without a diagnostic
     */
    public function __construct(
        public readonly Identifiers $identifiers,
        public readonly array $relations = [],
        public readonly array $extensions = [],
        public readonly ?self $parent = null,
        public readonly ?QueryContext $queries = null,
        public readonly array $merged = [],
        public readonly ?array $outputs = null,
        public readonly bool $detached = false,
    ) {
    }

    /**
     * Resolves a stored program variable or trigger row first, as MySQL does inside stored programs, then a relation column.
     * @param list<string> $parts
     * @throws SemanticException
     */
    public function column(array $parts, Node|Token $source): Expression
    {
        return Statement\Routine\Program\ProgramNamespace::lookup($this, $parts, $source) ?? $this->relationColumn($parts, $source);
    }

    /**
     * Resolves a column of the visible relations, a USING column, or an outer scope column.
     * @param list<string> $parts
     * @throws SemanticException
     */
    public function relationColumn(array $parts, Node|Token $source): Expression
    {
        $name = $parts[count($parts) - 1];
        $qualifiers = array_slice($parts, 0, -1);
        if ($qualifiers === [] && ($merged = Query\Joining\RowNamespace::resolve($this, $name, $source)) !== null) {
            return $merged;
        }
        $matches = [];
        foreach ($this->relations as $relation) {
            if (!$this->matches($relation, $qualifiers)) {
                continue;
            }
            foreach ($relation->declaration->columns as $ordinal => $column) {
                if ($this->identifiers->equal($column->name, $name)) {
                    $matches[] = [$relation, $ordinal];
                }
            }
        }
        if ($matches === [] && $this->parent !== null && ($qualifiers === [] || array_filter($this->relations, fn (TableUse $relation): bool => $this->matches($relation, $qualifiers)) === [])) {
            return $this->parent->column($parts, $source);
        }
        if ($matches === [] && ($literal = (new LiteralBinder($this->identifiers->dialect))->fallback($source)) !== null) {
            return $literal;
        }
        if (count($matches) !== 1) {
            return $this->unmatched($parts, $matches !== [], $source);
        }
        return $this->reference($matches[0][0], $matches[0][1], [...$qualifiers, $name], $source);
    }

    /**
     * References one column of a visible relation by its position, as a star expansion does when the relation repeats a column name.
     * @param list<string> $parts The written name of the reference
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function reference(TableUse $relation, int $ordinal, array $parts, Node|Token $source): Expression
    {
        $binding = new ColumnBinding($relation->id, $relation->declaration, $relation->declaration->columns[$ordinal]);
        if ($relation instanceof \SqlSemantics\Model\Relation\TriggerRow) {
            return new \SqlSemantics\Model\Scalar\Reference\TriggerColumn(new \SqlSemantics\Model\Scalar\ExpressionFacts($binding->column->type, $binding->column->nullability), $source, $binding, $relation->version);
        }
        $extensions = $this->extensions[$binding->relationId] ?? [];
        $origins = isset($relation->resultExpressions()[$ordinal]) ? [$relation->resultExpressions()[$ordinal]] : [];

        return new \SqlSemantics\Model\Scalar\Reference\ColumnReference(new \SqlSemantics\Model\Scalar\ExpressionFacts($binding->column->type, $extensions === [] ? $binding->column->nullability : Nullability::MaybeNull, $extensions), $source, $binding, $origins, $parts);
    }

    /**
     * Keeps a name that matches no column, or several, as an unresolved reference; the problem is diagnosed unless the scope is detached and nothing matched.
     * @param list<string> $parts
     * @throws SemanticException
     */
    public function unmatched(array $parts, bool $ambiguous, Node|Token $source): Expression
    {
        if ($ambiguous || !$this->detached) {
            $this->diagnostics()->report($ambiguous ? 'ambiguous-column' : 'unknown-column', 'Cannot resolve column unambiguously: ' . implode('.', $parts), $source);
        }
        return new \SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference(new \SqlSemantics\Model\Scalar\ExpressionFacts(\SqlSemantics\Type\TypeDescriptor::builtin($this->identifiers->dialect, 'unknown'), Nullability::Unknown, []), $source, $parts);
    }

    /**
     * Shares diagnostics across nested scopes.
     */
    public function diagnostics(): Analysis\Diagnostics
    {
        return $this->queries?->tables->diagnostics ?? $this->parent?->diagnostics() ?? new Analysis\Diagnostics();
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
     * Joins two FROM operands; MySQL and PostgreSQL reject a repeated item name, SQLite reports it.
     * @throws SemanticException
     * @throws \SqlSemantics\InvalidSql
     */
    public function combine(self $right, Node $source): self
    {
        if ($this->identifiers->dialect !== \SqlSemantics\Dialect::Sqlite) {
            FromBinder::unique([...$this->relations, ...$right->relations]);
        }
        foreach ($this->identifiers->dialect === \SqlSemantics\Dialect::Sqlite ? $this->relations : [] as $leftRelation) {
            foreach ($right->relations as $rightRelation) {
                if ($this->identifiers->relationEqual($leftRelation->alias ?? $leftRelation->declaration->name, $rightRelation->alias ?? $rightRelation->declaration->name)) {
                    $this->diagnostics()->report('duplicate-relation', 'Duplicate relation name in scope.', $source);
                }
            }
        }

        return new self($this->identifiers, [...$this->relations, ...$right->relations], $this->extensions + $right->extensions, $this->parent ?? $right->parent, $this->queries ?? $right->queries, $this->merged + $right->merged, Query\Joining\RowNamespace::combine($this, $right, $source), $this->detached || $right->detached);
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

        $merged = array_map(static fn (Expression $value): Expression => $value->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($value->type, Nullability::MaybeNull, [...$value->nullExtendedBy, $joinId])), $this->merged);
        return new self($this->identifiers, $this->relations, $extensions, $this->parent, $this->queries, $merged, $this->outputs === null ? null : Query\Joining\RowNamespace::extend($this->outputs, $joinId), $this->detached);
    }
    /**
     * @return array<int|string, Expression> Unqualified joined output columns
     */
    public function outputColumns(Node $source): array
    {
        $columns = [];
        foreach (Query\Joining\RowNamespace::read($this, $source) as $column) {
            if ($column->name !== null) {
                $columns[$column->name] = $column->expression;
            }
        }
        return $columns;
    }


    /**
     * Resolves a USING/NATURAL join's shared column under this dialect's name rules.
     */
    public function mergedColumn(string $name): ?Expression
    {
        foreach ($this->merged as $label => $expression) {
            if ($this->identifiers->equal((string) $label, $name)) {
                return $expression;
            }
        }
        return null;
    }
}
