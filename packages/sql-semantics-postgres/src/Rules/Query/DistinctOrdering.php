<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Validation\Equivalence;

/**
 * Checks that DISTINCT ON and ORDER BY agree, as PostgreSQL requires.
 *
 * Rule: PG-DISTINCT-ON-001. Each term denotes an output column: a position
 * or an output name denotes that column, an expression denotes the output
 * column that computes the same expression, and any other expression a
 * column of its own. Walking ORDER BY from the left, a term that is also a
 * DISTINCT ON term must not follow one that is not; when ORDER BY has such a
 * term that is not a DISTINCT ON term, every DISTINCT ON term must have
 * appeared before it. A violation is reported. Terminates: two passes over
 * finite lists.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-DISTINCT. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DistinctOrdering
{
    /**
     * Reports DISTINCT ON terms that do not match the leftmost ORDER BY terms.
     *
     * @param list<Scalar> $distinct The DISTINCT ON terms
     * @param list<Scalar> $order The ORDER BY terms of the selection
     * @param list<Field|OpenStar> $projection The output of the selection
     */
    public function check(array $distinct, array $order, array $projection, Derivation $derivation): void
    {
        if ($distinct === [] || $order === []) {
            return;
        }
        $keys = [];
        foreach ($distinct as $term) {
            $keys[] = $this->key($term, $projection, $derivation);
        }
        $covered = [];
        $skipped = false;
        foreach ($order as $term) {
            $index = $this->find($this->key($term, $projection, $derivation), $keys);
            if ($index === null) {
                $skipped = true;
            } elseif ($skipped) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::DistinctOnOrderBy));

                return;
            } else {
                $covered[$index] = true;
            }
        }
        if ($skipped && count($covered) < count($this->distinctKeys($keys))) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::DistinctOnOrderBy));
        }
    }

    /**
     * Answers the output column a term denotes: its position, or the expression when no output column computes it.
     *
     * @param list<Field|OpenStar> $projection
     */
    public function key(Scalar $term, array $projection, Derivation $derivation): int|Scalar
    {
        if ($term instanceof OutputPosition) {
            return (int) $term->value() - 1;
        }
        $name = (new Ordering())->bare($term);
        foreach ($name === null ? [] : (new Ordering())->named($projection, $name, $derivation) as $field) {
            return $field->position;
        }
        $bare = $this->unwrapped($term);
        foreach ($projection as $item) {
            if ($item instanceof Field && $item->expression !== null && (new Equivalence())->difference($this->unwrapped($item->expression), $bare) === null) {
                return $item->position;
            }
        }

        return $bare;
    }

    /**
     * Finds the first DISTINCT ON term with the same key, among the distinct keys.
     *
     * @param list<int|Scalar> $keys
     */
    public function find(int|Scalar $key, array $keys): ?int
    {
        foreach ($this->distinctKeys($keys) as $index => $candidate) {
            if (is_int($key) ? $candidate === $key : (!is_int($candidate) && (new Equivalence())->difference($candidate, $key) === null)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers the keys without repetitions, keeping the first of equal ones.
     *
     * @param list<int|Scalar> $keys
     * @return list<int|Scalar>
     */
    public function distinctKeys(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $seen = false;
            foreach ($result as $kept) {
                $seen = $seen || (is_int($key) ? $kept === $key : (!is_int($kept) && (new Equivalence())->difference($kept, $key) === null));
            }
            if (!$seen) {
                $result[] = $key;
            }
        }

        return $result;
    }

    /**
     * Answers an expression without the parentheses around it, which PostgreSQL does not keep.
     */
    public function unwrapped(Scalar $term): Scalar
    {
        while ($term instanceof Grouped) {
            $term = $term->operand;
        }

        return $term;
    }
}
