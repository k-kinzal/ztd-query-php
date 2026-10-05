<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Evidence;

use Deriver\Value\Term;

/**
 * A retained value owns its complete immutable provenance DAG.
 * @visibility root
 */
final class Expansion
{
    /**
     * Owns a value together with its immutable evidence DAG.
     */
    public function __construct(public readonly Term $value)
    {
    }
}
