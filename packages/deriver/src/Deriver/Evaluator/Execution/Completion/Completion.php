<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Completion;

use Deriver\Deriver\Evaluator\Term;

/**
 * A pending completion that a finally block can resume or replace.
 *
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Completion
{
    /**
     * @param CompletionKind $kind kind
     * @param Term|null $value value
     * @param int $target target
     * @param int $depth depth
     */
    public function __construct(
        public readonly CompletionKind $kind = CompletionKind::Normal,
        public readonly ?Term $value = null,
        public readonly int $target = 0,
        public readonly int $depth = 0,
    ) {
    }
}
