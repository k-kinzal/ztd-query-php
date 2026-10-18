<?php

declare(strict_types=1);

namespace Deriver\Deriver\Candidate;

/**
 * Whether a candidate is concrete or retains unresolved dependencies.
 * @visibility public
 * @example Reading the serialized status
 *     \Deriver\Deriver\Candidate\CandidateStatus::Analyzed->value // => 'analyzed'
 */
enum CandidateStatus: string
{
    case Analyzed = 'analyzed';
    case Partials = 'partials';
}
