<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Candidate;

use Deriver\Deriver\Candidate\CandidateStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CandidateStatus::class)]
final class CandidateStatusTest extends TestCase
{
    public function testStatusValuesIdentifyConcreteAndPartialCandidates(): void
    {
        self::assertSame(CandidateStatus::Analyzed, CandidateStatus::from('analyzed'));
        self::assertSame(CandidateStatus::Partials, CandidateStatus::from('partials'));
    }
}
