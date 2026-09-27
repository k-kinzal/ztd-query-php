<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\ExceptionRegion;
use Deriver\Evaluation\Completion;

/**
 * Dynamic phase of one exception region and its saved completion.
 *
 * @visibility root
 */
final class Handler
{
    /**
     * @param ExceptionRegion $region region
     * @param string $phase phase
     * @param Completion|null $saved saved
     */
    public function __construct(
        public readonly ExceptionRegion $region,
        public readonly string $phase = 'try',
        public readonly ?Completion $saved = null,
    ) {
    }
}
