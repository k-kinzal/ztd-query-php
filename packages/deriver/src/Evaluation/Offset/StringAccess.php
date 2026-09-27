<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Offset;

use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * A byte position whose assignment updates the containing string cell.
 * @visibility root
 */
final class StringAccess
{
    /**
     * @param Location $container Storage of the complete string
     * @param Term|null $key Evaluated offset, or append syntax
     */
    public function __construct(public readonly Location $container, public readonly ?Term $key)
    {
    }
}
