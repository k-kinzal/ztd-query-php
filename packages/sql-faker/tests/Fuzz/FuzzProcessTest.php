<?php

declare(strict_types=1);

namespace Tests\Integration\SqlFaker;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\SqlFaker\CoverageFixture;
use Tests\Fixtures\SqlFaker\FuzzProcess;

#[CoversNothing]
#[Large]
final class FuzzProcessTest extends TestCase
{
    public function testReviewedFindingIsReproducibleAndNeverReportedAsSuccessful(): void
    {
        $directory = CoverageFixture::directory();
        $input = 'fuzz/seeds/reviewed/sqlite/null-ordering.bin';
        try {
            $first = FuzzProcess::run(['run-single', 'fuzz/fuzz_sqlite_syntax.php', $input], $directory);
            $second = FuzzProcess::run(['run-single', 'fuzz/fuzz_sqlite_syntax.php', $input], $directory);
            self::assertSame(1, $first['exitCode'], $first['output']);
            self::assertSame(1, $second['exitCode'], $second['output']);
            self::assertStringContainsString('SyntaxFailure', $second['output']);
            self::assertStringContainsString('NULLS FIRST', $second['output']);
            self::assertSame(file_get_contents(dirname(__DIR__, 2) . '/' . $input), file_get_contents($directory . '/reports/failure.bin'));
        } finally {
            FuzzProcess::remove($directory);
        }
    }

    /**
     * @throws JsonException
     */
    public function testSuccessfulReplayRestoresHistoryWithoutCountingItAsCurrent(): void
    {
        $directory = CoverageFixture::directory();
        mkdir($directory . '/corpus');
        file_put_contents($directory . '/corpus/empty.bin', '');
        try {
            $arguments = ['fuzz', 'fuzz/fuzz_sqlite_syntax.php', $directory . '/corpus', '--max-runs=0'];
            $first = FuzzProcess::run($arguments, $directory);
            $second = FuzzProcess::run($arguments, $directory);
            self::assertSame(0, $first['exitCode'], $first['output']);
            self::assertSame(0, $second['exitCode'], $second['output']);
            $json = file_get_contents($directory . '/reports/run.json');
            self::assertNotFalse($json);
            $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($report);
            self::assertIsArray($report['coverage']);
            self::assertIsArray($report['coverage']['checkpoint']);
            self::assertIsArray($report['coverage']['current']);
            self::assertIsArray($report['coverage']['cumulative']);
            self::assertSame(['expectedInputs' => 1, 'completedInputs' => 1, 'complete' => true], $report['corpusReplay']);
            self::assertSame(1, $report['coverage']['checkpoint']['generationsObservedInRun']);
            self::assertSame(['accepted' => 1], $report['verification']);
            self::assertSame($report['coverage']['current']['reachedIds'], $report['coverage']['cumulative']['reachedIds']);
            self::assertNotNull($report['coverage']['restoredCheckpoint']);
        } finally {
            FuzzProcess::remove($directory);
        }
    }
}
