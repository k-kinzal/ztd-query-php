<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The type of a row of several values: a row constructor or a subquery of several columns (`ROW_RESULT`).
 *
 * Only the number of columns is part of the type: comparisons, IN and row
 * subqueries require rows of equal width.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 *
 * @visibility public
 * @example Reading the width of a row
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE (a, b) IS NULL');
 *     $query->facts->scalar($query->statement->where->operand)->type->descriptor->width // => 2
 */
final class Tuple implements TypeDescriptor
{
    use Snapshot;

    /**
     * @param int $width The number of columns; at least two
     */
    public function __construct(public readonly int $width)
    {
        Check::input($width >= 2, 'A row has at least two columns.');
    }

    /**
     * Names the type as the server's result type.
     */
    public function name(): string
    {
        return 'ROW';
    }
}
