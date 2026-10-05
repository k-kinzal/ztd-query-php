<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Result\Candidates\Candidate;
use Deriver\Result\Candidates\CandidateCollection;
use JsonException;
use LogicException;

/**
 * Mutates valid exports without decoding untyped data into the test body.
 */
final class ReplayMutations
{
    /**
     * @throws JsonException If a fixture export cannot be encoded
     * @throws LogicException If a fixture does not have two caller alternatives
     */
    public function apply(CandidateCollection $result, string $mutation): string
    {
        $candidates = $result->candidates;
        if ($mutation === 'caller') {
            $first = $candidates[0];
            $remaining = array_slice($first->evidence, 0, -1);
            if ($remaining === []) {
                throw new LogicException('Missing-caller mutation requires two evidence alternatives.');
            }
            $candidates[0] = new Candidate($first->term, $remaining);
        } elseif ($mutation === 'candidate') {
            array_pop($candidates);
        }
        $json = (new CandidateCollection($candidates, $result->reference))->toJson();
        return match ($mutation) {
            'hash' => str_replace(hash('sha256', '<?php function f($x){return $x?30:60;}function a(){return f(true);}function b(){return f(true);}function c(){return f(false);}'), str_repeat('0', 64), $json),
            'model' => str_replace('"models":[]', '"models":{"replacement":"2"}', $json),
            'edge' => preg_replace('/"root":"[^"]+"/', '"root":"missing"', $json) ?? $json,
            default => $json,
        };
    }
}
