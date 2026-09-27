<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use SqlSemantics\Statement\Element;

/**
 * Composes the values every dialect has, under stable names, from PHP data and other values.
 *
 * A database package implements this for its own generated model and narrows
 * the return types to its roles. Names and literals are spelled as the
 * release and mode read them: a name is quoted when the release would read
 * it as a keyword or fold or reject its characters, and a string is escaped
 * as the session reads escapes. An operand that binds more weakly than the
 * position it is placed in is parenthesized.
 *
 * @visibility public
 * @example Composing a comparison with the builder of any database
 *     $condition = static fn (\SqlSemantics\Core\Builder $builder): string => \SqlSemantics\Statement\Writer::render($builder->compare($builder->column('select'), '=', $builder->string("it's")));
 *     $condition instanceof \Closure // => true
 */
interface Builder
{
    /**
     * A name in the role that column, table, and alias names share.
     *
     * @throws CompositionException When the name is empty or cannot be spelled in the language
     */
    public function identifier(string $name): Element;

    /**
     * A reference to a column, qualified by the names before the last.
     *
     * @throws CompositionException When a part is empty or the release has no form for the qualification
     */
    public function column(string ...$parts): Element;

    /**
     * A reference to a table, qualified by the names before the last.
     *
     * @throws CompositionException When a part is empty or the release has no form for the qualification
     */
    public function table(string ...$parts): Element;

    /**
     * A string literal holding exactly the value.
     *
     * @throws CompositionException When the language cannot hold a byte of the value in a string literal
     */
    public function string(string $value): Element;

    /**
     * An integer literal.
     */
    public function integer(int $value): Element;

    /**
     * A numeric literal that the language reads as a non-integer number.
     *
     * @throws CompositionException When the value is not finite
     */
    public function float(float $value): Element;

    /**
     * The language's boolean truth values.
     */
    public function boolean(bool $value): Element;

    /**
     * The NULL literal.
     */
    public function null(): Element;

    /**
     * A binary string literal holding exactly the bytes.
     */
    public function binary(string $bytes): Element;

    /**
     * A bound parameter marker, numbered from one where the language numbers them.
     *
     * @throws CompositionException When the position is below one
     */
    public function parameter(int $position = 1): Element;

    /**
     * The conjunction of two conditions.
     */
    public function and(Element $left, Element $right): Element;

    /**
     * The disjunction of two conditions.
     */
    public function or(Element $left, Element $right): Element;

    /**
     * The negation of a condition.
     */
    public function not(Element $operand): Element;

    /**
     * A comparison with one of `=`, `<>`, `!=`, `<`, `<=`, `>`, and `>=`.
     *
     * @throws CompositionException When the operator is not a comparison the language has
     */
    public function compare(Element $left, string $operator, Element $right): Element;

    /**
     * An expression in parentheses.
     */
    public function parenthesized(Element $expression): Element;

    /**
     * The rows of both queries, duplicates kept.
     */
    public function unionAll(Element $left, Element $right): Element;

    /**
     * A common table expression naming the rows of a query, with optional column names.
     *
     * @param list<string> $columns
     *
     * @throws CompositionException When the release has no common table expressions
     */
    public function cte(string $name, Element $query, array $columns = []): Element;

    /**
     * A query preceded by a WITH clause holding the common table expressions.
     *
     * The query is a complete SELECT command or query expression of the
     * language; a query that already has a WITH clause keeps its expressions
     * after the given ones.
     *
     * @param list<Element> $ctes Values answered by cte()
     *
     * @throws CompositionException When the release has no common table expressions or the query has no form that takes one
     */
    public function with(array $ctes, Element $query): Element;
}
