<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Cursor;

/**
 * Every remaining row in the requested direction, without an artificial numeric count.
 * @visibility public
 */
final class RemainingRows implements Movement
{
    /**
     * Retains which end of the cursor the request advances toward.
     */
    public function __construct(public readonly ScanDirection $direction)
    {
    }
}
