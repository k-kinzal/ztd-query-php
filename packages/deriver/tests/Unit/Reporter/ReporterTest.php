<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Reporter\JsonReporter;
use Deriver\Reporter\Reporter;
use Deriver\Reporter\TextReporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ReporterTest extends TestCase
{
    #[DataProvider('providerReporters')]
    public function testReportAcceptsACollectionWithoutAnAnalysisSession(Reporter $reporter): void
    {
        $collection = new CandidateCollection([], new ResultRef('r'));
        self::assertStringEndsWith("\n", $reporter->report($collection));
        self::assertCount(0, $collection);
    }

    /**
     * @return list<array{Reporter}>
     */
    public static function providerReporters(): array
    {
        return [[new TextReporter()], [new JsonReporter()]];
    }
}
