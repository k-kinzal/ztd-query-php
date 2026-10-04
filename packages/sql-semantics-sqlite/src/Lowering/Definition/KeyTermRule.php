<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlSemantics\Platform\Sqlite\Rules\Definition\KeyTerms;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the string literals that SQLite reads as column names in key constraints and index terms.
 *
 * Rule: SQLITE-KEY-TERM-LOWER-001. Scope: the `sortlist` of PRIMARY KEY and
 * UNIQUE table constraints and of CREATE INDEX, after the ordering rule
 * lowered it. A term whose expression is a string literal under parentheses
 * and COLLATE clauses (any number for a key constraint, at most one for an
 * index term, SQLITE-KEY-TERM-001) is rebuilt around a LiteralColumn that
 * holds the recorded literal leaf; the parentheses and collations are
 * rebuilt in the same order. Every other term is left as it is. Terminates:
 * the wrappers of a finite expression are peeled once and put back once.
 * Source: https://sqlite.org/lang_createtable.html#the_primary_key,
 * https://sqlite.org/lang_createindex.html (and `sqlite3StringToId()` in
 * build.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class KeyTermRule
{
    /**
     * Lowers the terms of a PRIMARY KEY or UNIQUE constraint.
     *
     * @param list<SortTerm> $terms
     * @return list<SortTerm>
     */
    public function keyTerms(array $terms): array
    {
        return array_map(fn (SortTerm $term): SortTerm => $this->term($term, null), $terms);
    }

    /**
     * Lowers the terms of a CREATE INDEX statement.
     *
     * @param list<SortTerm> $terms
     * @return list<SortTerm>
     */
    public function indexTerms(array $terms): array
    {
        return array_map(fn (SortTerm $term): SortTerm => $this->term($term, 1), $terms);
    }

    /**
     * Rebuilds a term around a literal column when its expression is a string under the admitted wrappers.
     *
     * @param int|null $collations How many COLLATE clauses may lie above the string; null for any number
     */
    public function term(SortTerm $term, ?int $collations): SortTerm
    {
        if (!(new KeyTerms())->string($term->expression, $collations)) {
            return $term;
        }

        return new SortTerm($this->expression($term->expression), $term->direction, $term->nulls);
    }

    /**
     * Rebuilds the parentheses and collations of an expression around a literal column in place of its string.
     */
    public function expression(Scalar $expression): Scalar
    {
        $wrappers = [];
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            $wrappers[] = $expression;
            $expression = $expression->operand;
        }
        $rebuilt = $expression instanceof TextLiteral ? new LiteralColumn($expression) : $expression;
        foreach (array_reverse($wrappers) as $wrapper) {
            $rebuilt = $wrapper instanceof Grouped ? new Grouped($rebuilt) : new Collate($rebuilt, $wrapper->collation);
        }

        return $rebuilt;
    }
}
