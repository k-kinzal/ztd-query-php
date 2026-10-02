<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The array part of a type name: bracket pairs, or the keyword ARRAY with at most one size.
 *
 * `integer[3][]`, `integer ARRAY[3]` and `integer ARRAY` all make the type an
 * array of integers; the dimensions written are kept as documentation, as the
 * server keeps them. The keyword form is the SQL standard spelling of one
 * dimension.
 * Source: https://www.postgresql.org/docs/17/arrays.html#ARRAYS-DECLARATION.
 *
 * @visibility public
 * @example Reading the dimensions of a bracket form
 *     $array = new \SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier([new \SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound(), new \SqlSemantics\Platform\PostgreSql\Statement\Type\ArrayBound()]);
 *     [count($array->bounds), $array->keyword] // => [2, false]
 * @example Rejecting brackets with no dimension
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Type\ArraySpecifier([]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ArraySpecifier implements Node
{
    use Snapshot;

    /**
     * @var list<ArrayBound> The dimensions written with brackets
     */
    public readonly array $bounds;

    /**
     * @param list<ArrayBound> $bounds The dimensions written with brackets
     * @param bool $keyword Whether the keyword ARRAY is written
     */
    public function __construct(array $bounds, public readonly bool $keyword = false)
    {
        $this->bounds = Check::listOf($bounds, ArrayBound::class, 'Array dimensions are array bounds.');
        Check::input($keyword ? count($this->bounds) <= 1 && ($this->bounds === [] || $this->bounds[0]->size !== null) : $this->bounds !== [], 'The ARRAY keyword takes at most one sized dimension; brackets take at least one dimension.');
    }

    /**
     * Writes the keyword and the dimensions.
     */
    public function render(Output $out): void
    {
        if ($this->keyword) {
            $out->keyword('ARRAY');
        }
        foreach ($this->bounds as $bound) {
            $out->node($bound);
        }
    }
}
