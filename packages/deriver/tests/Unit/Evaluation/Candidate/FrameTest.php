<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;

#[CoversNothing]
final class FrameTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testIterationRetainsTheOriginalFrameAndSelectsDistinctDefinitionVersions(): void
    {
        $e = CandidateFixture::evaluator();
        $f = CandidateFixture::frame($e);
        $next = $f->iteration(1, 2);
        self::assertSame([], $f->iterations);
        self::assertSame([1 => 2], $next->iterations);
        self::assertNotSame($f->identity, $next->identity);
    }
}
