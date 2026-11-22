<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\TypeDescriptor;

/**
 * The type of a row of several values: a row constructor or a subquery of several columns (`ROW_RESULT`).
 *
 * Only the number of columns is part of the type: comparisons, IN and row
 * subqueries require rows of equal width.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 *
 * @visibility SqlSemantics
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
