<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The bracketed items of an array constructor: `[a, b]`, or the sub-arrays of a multidimensional array `[[a], [b]]`.
 *
 * Mirrors one level of PostgreSQL's `A_ArrayExpr`: an inner level is written
 * without the ARRAY keyword. A level holds either values or sub-arrays, or
 * nothing.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ARRAY-CONSTRUCTORS.
 *
 * @visibility public
 * @example Reading the sub-arrays of a two-dimensional array
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT ARRAY[[1, 2], [3, 4]]');
 *     [count($query->field(0)->expression->items->nested), $query->field(0)->type->descriptor->name()] // => [2, 'integer[]']
 */
final class ArrayItems implements Node
{
    use Snapshot;

    /**
     * @var list<Scalar> The values in order, or none when the level holds sub-arrays
     */
    public readonly array $values;

    /**
     * @var list<ArrayItems> The sub-arrays in order, or none when the level holds values
     */
    public readonly array $nested;

    /**
     * @param list<Scalar> $values The values in order
     * @param list<ArrayItems> $nested The sub-arrays in order
     */
    public function __construct(array $values = [], array $nested = [])
    {
        $this->values = Check::listOf($values, Scalar::class, 'Array values are expressions.');
        $this->nested = Check::listOf($nested, self::class, 'Sub-arrays are bracketed items.');
        Check::input($this->values === [] || $this->nested === [], 'An array level holds values or sub-arrays, not both.');
    }

    /**
     * Writes the values or the sub-arrays in brackets.
     */
    public function render(Output $out): void
    {
        $out->symbol('[')->list($this->values === [] ? $this->nested : $this->values)->symbol(']');
    }
}
