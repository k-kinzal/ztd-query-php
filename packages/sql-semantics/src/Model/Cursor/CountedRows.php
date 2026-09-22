<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * A fixed number of rows in a specified scan direction.
 * @visibility public
 */
final class CountedRows implements Movement
{
    /**
     * A negative count reverses the scan; the number remains unevaluated here.
     */
    public function __construct(public readonly ScanDirection $direction, public readonly IntegerOffset $count)
    {
    }
}
