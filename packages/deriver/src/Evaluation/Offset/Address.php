<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Offset;

use Deriver\Value\Term;

/**
 * Retains the evaluated key before container-dependent offset conversion.
 * @visibility root
 */
final class Address
{
    /**
     * @param string $parent Parent address register
     * @param Term|null $key Evaluated key, or null for append syntax
     */
    public function __construct(public readonly string $parent, public readonly ?Term $key)
    {
    }
}
