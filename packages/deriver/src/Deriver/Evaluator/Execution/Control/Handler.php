<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Control;

use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Lowerer\Graph\ExceptionRegion;

/**
 * Dynamic phase of one exception region and its saved completion.
 *
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Handler
{
    /**
     * @param ExceptionRegion $region region
     * @param HandlerPhase $phase phase
     * @param Completion|null $saved saved
     */
    public function __construct(
        public readonly ExceptionRegion $region,
        public readonly HandlerPhase $phase = HandlerPhase::Try,
        public readonly ?Completion $saved = null,
    ) {
    }
}
