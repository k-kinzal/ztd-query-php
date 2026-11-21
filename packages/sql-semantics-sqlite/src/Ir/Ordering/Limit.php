<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Ordering;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Bounds the ordered result and preserves the lexical order of parameter labels.
 *
 * @visibility public
 * @example An unbounded count with a numbered offset
 *     $limit = new \SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit(new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('-1'), new \SqlSemantics\Platform\Sqlite\Ir\Parameter('2'));
 *     $limit->offset->slot // => 2
 */
final class Limit
{
    use Snapshot;

    /**
     * Establishes the count and offset roles and the order in which labels occur.
     */
    public function __construct(
        public readonly IntegerLiteral|Parameter $count,
        public readonly IntegerLiteral|Parameter|null $offset = null,
        public readonly bool $offsetFirst = false,
    ) {
        Check::input(!$offsetFirst || $offset !== null, 'An offset-first limit requires an offset.');
    }
}
