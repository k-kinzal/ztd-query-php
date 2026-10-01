<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\Value\Term;

/**
 * A pending completion that a finally block can resume or replace.
 *
 * @visibility root
 */
final class Completion
{
    /**
     * @param string $kind kind
     * @param Term|null $value value
     * @param int $target target
     * @param int $depth depth
     */
    public function __construct(
        public readonly string $kind = 'normal',
        public readonly ?Term $value = null,
        public readonly int $target = 0,
        public readonly int $depth = 0,
    ) {
    }
}
