<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The type of a row value: an ordered group of two or more values that is no single storage class.
 *
 * Source: https://sqlite.org/rowvalue.html.
 *
 * @visibility public
 * @example Reading the width of a row value
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT (1, 2) = (1, 2)');
 *     $query->facts->scalar($query->statement->columns[0]->expression->left)->type->descriptor->width // => 2
 */
final class Vector implements TypeDescriptor
{
    use Snapshot;

    /**
     * @param int $width The number of values in the row
     */
    public function __construct(public readonly int $width)
    {
        Check::input($width >= 2, 'A row value has at least two values.');
    }

    /**
     * Names the type with its width.
     */
    public function name(): string
    {
        return 'ROW(' . $this->width . ')';
    }
}
