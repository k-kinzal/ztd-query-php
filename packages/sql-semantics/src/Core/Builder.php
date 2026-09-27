<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use SqlSemantics\Statement\Declaration\TypeDescriptor;
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
     * A numeric literal that the language reads as a non-integer number and that reads back as exactly the double.
     *
     * The digits are the fewest that denote the same double, whatever the
     * `precision` settings, and negative zero is written negated.
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
     * A bound parameter marker: numbered from one where the language numbers them, or the named placeholder `:name`.
     *
     * @throws CompositionException When the position is below one, the name is not a name, or the language reads no named placeholder
     */
    public function parameter(int|string $marker = 1): Element;

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
     * `IS NULL`, or `IS NOT NULL` when negated.
     */
    public function isNull(Element $operand, bool $negated = false): Element;

    /**
     * Membership in a list of values: `IN`, or `NOT IN` when negated.
     *
     * @param list<Element> $values
     *
     * @throws CompositionException When the release reads no such list, such as an empty one
     */
    public function in(Element $operand, array $values, bool $negated = false): Element;

    /**
     * A searched CASE: the result of the first condition that holds, else the default, else NULL.
     *
     * @param list<array{Element, Element}> $whens Each condition with its result, in order; at least one
     *
     * @throws CompositionException When there is no condition
     */
    public function case(array $whens, ?Element $else = null): Element;

    /**
     * A call of a function by its name, spelled bare when the release reads it as that name and quoted otherwise.
     *
     * A bare name the grammar gives its own form, such as COALESCE where it
     * is a keyword, is written in that form.
     *
     * @param list<Element> $arguments
     *
     * @throws CompositionException When the name is empty or the release reads no call of it with these arguments
     */
    public function call(string $name, array $arguments = []): Element;

    /**
     * `CAST` of an operand to a type, spelled as the release's CAST names that type.
     *
     * The target names exactly the type: a type CAST has no target for, or a
     * fact of the type the target cannot state, is an error, never a nearby
     * type.
     *
     * @throws CompositionException When the release's CAST has no target of exactly this type
     */
    public function cast(Element $operand, TypeDescriptor $type): Element;

    /**
     * A SELECT of columns, from a table when one is given, keeping the rows a condition holds for when one is given.
     *
     * The query is a complete command, like one analyzed from SQL.
     *
     * @param list<array{Element, string|null}> $columns Each expression with its alias, or null for none; at least one
     * @param Element|null $from A table, such as one answered by table()
     * @param Element|null $where The condition
     *
     * @throws CompositionException When there is no column or the release reads no such query
     */
    public function select(array $columns, ?Element $from = null, ?Element $where = null): Element;

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
