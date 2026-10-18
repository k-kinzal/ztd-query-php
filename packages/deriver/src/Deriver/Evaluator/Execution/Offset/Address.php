<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Term;

/**
 * Retains the evaluated key before container-dependent offset conversion.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
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
