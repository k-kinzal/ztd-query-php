<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Type;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeDescriptor;

/**
 * The type of a row value: an ordered group of two or more values that is no single storage class.
 *
 * Source: https://sqlite.org/rowvalue.html.
 *
 * @visibility SqlSemantics
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
