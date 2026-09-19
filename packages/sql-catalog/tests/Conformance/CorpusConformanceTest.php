<?php

declare(strict_types=1);

namespace Tests\Conformance;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analyzer;
use SqlCatalog\Conformance\ConformanceChecker;
use SqlCatalog\Conformance\ObservedStatement;
use SqlCatalog\Conformance\RecordingReader;

#[CoversClass(ConformanceChecker::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(RecordingReader::class)]
#[UsesClass(ObservedStatement::class)]
#[UsesClass(\SqlCatalog\AnalysisOptions::class)]
#[UsesClass(\SqlCatalog\Catalog\Catalog::class)]
#[UsesClass(\SqlCatalog\Catalog\CatalogEntry::class)]
#[UsesClass(\SqlCatalog\Catalog\Placeholder::class)]
#[UsesClass(\SqlCatalog\Catalog\ValueDomain::class)]
#[UsesClass(\SqlCatalog\Conformance\ConformanceReport::class)]
#[UsesClass(\SqlCatalog\Conformance\PatternMatcher::class)]
#[Medium]
final class CorpusConformanceTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testEveryStatementTheCorpusIssuesIsInTheCatalog(): void
    {
        $root = dirname(__DIR__, 2);
        $recording = (string) shell_exec('php ' . escapeshellarg($root . '/corpus/run.php'));
        $observed = (new RecordingReader())->read($recording);
        $catalog = (new Analyzer())->analyzePaths([$root . '/corpus/app'], null, $root);

        $report = (new ConformanceChecker())->check($catalog, $observed);

        self::assertGreaterThan(0, $report->observed, 'the corpus has to issue statements to check against');
        self::assertSame([], array_map(
            static fn (ObservedStatement $statement): string => $statement->source . ': ' . $statement->normalized(),
            $report->uncovered,
        ));
        self::assertSame([], $report->valueMismatches);
        self::assertTrue($report->isSound(), $report->display());
    }

    /**
     * @throws JsonException
     */
    public function testTheCatalogPinsDownMostOfWhatTheCorpusIssues(): void
    {
        $root = dirname(__DIR__, 2);
        $recording = (string) shell_exec('php ' . escapeshellarg($root . '/corpus/run.php'));
        $observed = (new RecordingReader())->read($recording);
        $catalog = (new Analyzer())->analyzePaths([$root . '/corpus/app'], null, $root);

        $report = (new ConformanceChecker())->check($catalog, $observed);

        self::assertGreaterThanOrEqual(0.8, $report->precision(), $report->display());
        self::assertSame(1.0, $report->coverage(), $report->display());
    }

    /**
     * @throws JsonException
     */
    public function testTheValuesTheCorpusBindsAreAdmittedByTheCatalog(): void
    {
        $root = dirname(__DIR__, 2);
        $recording = (string) shell_exec('php ' . escapeshellarg($root . '/corpus/run.php'));
        $observed = (new RecordingReader())->read($recording);
        $catalog = (new Analyzer())->analyzePaths([$root . '/corpus/app'], null, $root);

        $bound = array_sum(array_map(
            static fn (ObservedStatement $statement): int => count($statement->positional) + count($statement->named),
            $observed,
        ));

        self::assertGreaterThan(0, $bound, 'the corpus has to bind values to check against');
        self::assertSame([], (new ConformanceChecker())->check($catalog, $observed)->valueMismatches);
    }
}
