<?php

declare(strict_types=1);

namespace Deriver\Reporter;

use Deriver\Deriver\Candidate\CandidateCollection;

/**
 * Formats an existing candidate collection without running or changing its analysis.
 * @visibility public
 * @example Selecting a text reporter
 *     (new \Deriver\Reporter\TextReporter()) instanceof \Deriver\Reporter\Reporter // => true
 */
interface Reporter
{
    /**
     * Returns the complete report, including its final newline.
     */
    public function report(CandidateCollection $candidates): string;
}
