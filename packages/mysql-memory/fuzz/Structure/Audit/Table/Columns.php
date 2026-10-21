<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Table;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies columns for one missing relation without borrowing another scope's names.
 * TABLE subqueries obtain their width from the unchanged expression that consumes
 * them. Conflicting requirements remain visible when that expression is retried.
 */
final class Columns
{
    /**
     * @return non-empty-list<Name> Existing requested identities, or independent fixture columns
     */
    public function names(M\Statement|Scalar $node, QualifiedName $name, string $database): array
    {
        if ($node instanceof Scalar) {
            foreach ((new \Fuzz\Structure\Context\Columns())->bindings($node) as $binding) {
                if ($this->same($binding['relation']->name, $name, $database)) {
                    return $binding['columns'];
                }
            }
        }
        $width = $this->width($node, $name, $database);
        return $width === null ? (new Inputs())->columns($node) : array_map(static fn (int $index): Name => new Name('fixture_value_' . $index), range(1, max(1, $width)));
    }

    /**
     * Finds a concrete TABLE operand's requested width without resolving the catalog.
     */
    public function width(Node $node, QualifiedName $name, string $database): ?int
    {
        foreach ((new Inputs())->values($node) as $value) {
            if ($value instanceof M\Expression\Subquery\ScalarSubquery && $this->table($value->query, $name, $database)) {
                return 1;
            }
            if (($value instanceof M\Expression\Subquery\InQuery || $value instanceof M\Expression\Subquery\QuantifiedComparison) && $this->table($value->query, $name, $database)) {
                $parts = (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->parts($value->operand);
                if ($parts !== null) {
                    return max(1, count($parts));
                }
            }
        }
        return null;
    }

    /**
     * Follows only wrappers that preserve a TABLE query's output columns.
     */
    public function table(Query $query, QualifiedName $name, string $database): bool
    {
        while ($query instanceof M\Query\QueryStatement || $query instanceof M\Query\ParenthesizedQuery || $query instanceof M\Query\QueryExpression) {
            $query = $query instanceof M\Query\QueryExpression ? $query->body : $query->query;
        }
        return $query instanceof M\Query\ExplicitTable && $this->same($query->table, $name, $database);
    }

    /**
     * Compares case-sensitive table identities rather than PHP object identities.
     */
    public function same(QualifiedName $left, QualifiedName $right, string $database): bool
    {
        return $left->name->value === $right->name->value && ($left->schema->value ?? $database) === ($right->schema->value ?? $database);
    }
}
