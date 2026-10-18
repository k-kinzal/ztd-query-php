<?php

declare(strict_types=1);

namespace Deriver\Reporter;

use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Evaluator\Term;
use Override;

/**
 * Prints candidate values and distinguishes unresolved expressions from concrete results.
 * @visibility public
 * @example Reporting an empty candidate set
 *     $candidates = new \Deriver\Deriver\Candidate\CandidateCollection([], new \Deriver\Deriver\Candidate\ResultRef('r'));
 *     (new \Deriver\Reporter\TextReporter())->report($candidates) // => "No candidates.\n"
 */
final class TextReporter implements Reporter
{
    /**
     * Renders native PHP literals and a node table for partial expressions.
     */
    #[Override]
    public function report(CandidateCollection $candidates): string
    {
        $lines = [];
        foreach ($candidates as $index => $candidate) {
            $result = $candidate->result instanceof Term ? (new TermGraph())->encode($candidate->result) : $candidate->result;
            $lines[] = '[' . $index . '] ' . $candidate->type->value . ' ' . $candidate->type_name;
            $lines[] = var_export($result, true);
            $lines[] = 'Evidence: ' . count($candidate->evidence) . ' derivation(s).';
        }
        if ($lines === []) {
            $lines[] = 'No candidates.';
        }
        if ($candidates->interrupted) {
            $lines[] = 'Exploration interrupted; the candidate collection is incomplete.';
        }
        return implode("\n", $lines) . "\n";
    }
}
