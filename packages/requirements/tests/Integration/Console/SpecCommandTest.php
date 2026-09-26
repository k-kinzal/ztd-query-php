<?php

declare(strict_types=1);

namespace Tests\Integration\Console;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\Application;
use Requirements\Console\CommandHandler;
use Requirements\Console\CommandLine;
use Requirements\Console\Executor;
use Requirements\Console\ItemRecord;
use Requirements\Console\Reporter;
use Requirements\Console\SpecificationTable;
use Requirements\Input\Fields;
use Requirements\Verification\Verifier;
use Tests\Fake\CommandLine as Cli;
use Tests\Fake\PhpUnitSuite;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Application::class)]
#[UsesClass(CommandLine::class)]
#[UsesClass(CommandHandler::class)]
#[UsesClass(Executor::class)]
#[UsesClass(ItemRecord::class)]
#[UsesClass(Reporter::class)]
#[UsesClass(SpecificationTable::class)]
#[UsesClass(Verifier::class)]
#[UsesClass(Fields::class)]
#[Large]
final class SpecCommandTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testRunCountsPassingTargetsPerSpecificationSeparatelyFromDataSets(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'runners' => ['unit' => ['extension' => 'phpunit', 'command' => PhpUnitSuite::write($project->directory)]],
        ]);
        $item = [...ProjectDirectory::item(), 'kind' => 'specification', 'status' => 'supported', 'reason' => 'A documented decision.'];
        $items = [
            [...$item, 'id' => 'PASS', 'tests' => [['runner' => 'unit', 'target' => 'Sample\\PassingTest::testPass'], ['runner' => 'unit', 'target' => 'Sample\\PassingTest::testData']]],
            [...$item, 'id' => 'PARTIAL', 'tests' => [['runner' => 'unit', 'target' => 'Sample\\PassingTest::testPass'], ['runner' => 'unit', 'target' => 'Sample\\PassingTest::testFailure'], ['runner' => 'unit', 'target' => 'Sample\\PassingTest::testSkip']]],
            [...$item, 'id' => 'MISSING', 'tests' => [['runner' => 'unit', 'target' => 'Sample\\PassingTest::testMissing']]],
            [...$item, 'id' => 'SHARED', 'tests' => [['runner' => 'unit', 'target' => 'Sample\\PassingTest::testData'], ['runner' => 'unit', 'target' => 'Sample\\PassingTest::testData']]],
            [...$item, 'id' => 'UNVERIFIED', 'tests' => []],
            [...$item, 'id' => 'UNSUPPORTED', 'status' => 'unsupported', 'tests' => [['runner' => 'unit', 'target' => 'Sample\\PassingTest::testFailure']]],
            [...$item, 'id' => 'REQ', 'kind' => 'requirement', 'tests' => []],
        ];
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => $items]);
        $process = Cli::run(['spec', '--json'], $project->directory);
        self::assertSame(1, $process->getExitCode(), $process->getErrorOutput());
        $report = Fields::mapping(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR), 'report');
        self::assertFalse($report['passed']);
        self::assertFalse($report['no_test']);
        $rows = Fields::mapping($report['specifications'], 'specifications');
        self::assertSame(['PASS' => 'passed', 'PARTIAL' => 'failed', 'MISSING' => 'failed', 'SHARED' => 'passed', 'UNVERIFIED' => 'unverified', 'UNSUPPORTED' => 'unsupported', 'REQ' => 'not-applicable'], array_column($rows, 'status', 'id'));
        self::assertSame(['PASS' => 2, 'PARTIAL' => 1, 'MISSING' => 0, 'SHARED' => 1, 'UNVERIFIED' => 0, 'UNSUPPORTED' => null, 'REQ' => null], array_column($rows, 'passed_targets', 'id'));
        self::assertSame(['PASS' => 2, 'PARTIAL' => 3, 'MISSING' => 1, 'SHARED' => 1, 'UNVERIFIED' => 0, 'UNSUPPORTED' => 1, 'REQ' => 0], array_column($rows, 'total_targets', 'id'));
        self::assertSame(['PASS' => 3, 'PARTIAL' => 3, 'MISSING' => 0, 'SHARED' => 2, 'UNVERIFIED' => 0, 'UNSUPPORTED' => 0, 'REQ' => 0], array_column($rows, 'tests', 'id'));
        self::assertSame(['PASS' => 'manual', 'PARTIAL' => 'manual', 'MISSING' => 'manual', 'SHARED' => 'manual', 'UNVERIFIED' => 'manual', 'UNSUPPORTED' => 'manual', 'REQ' => 'manual'], array_column($rows, 'source', 'id'));
        self::assertSame(['PASS' => 'supported', 'PARTIAL' => 'supported', 'MISSING' => 'supported', 'SHARED' => 'supported', 'UNVERIFIED' => 'supported', 'UNSUPPORTED' => 'unsupported', 'REQ' => 'supported'], array_column($rows, 'support', 'id'));
        $table = Cli::run(['spec', '--id=PARTIAL', '--no-ansi'], $project->directory);
        self::assertSame(1, $table->getExitCode());
        self::assertStringContainsString('1/3', $table->getOutput());
        self::assertStringNotContainsString('2/2', $table->getOutput());
        $passing = Cli::run(['spec', '--id=PASS', '--no-ansi'], $project->directory);
        self::assertSame(0, $passing->getExitCode());
        self::assertStringContainsString('2/2', $passing->getOutput());
        $requirements = Cli::run(['spec', '--kind=requirement', '--no-ansi'], $project->directory);
        self::assertSame(0, $requirements->getExitCode());
        self::assertStringContainsString('REQ', $requirements->getOutput());
        self::assertStringContainsString('-/0', $requirements->getOutput());
    }

    /**
     * @throws JsonException
     */
    public function testRunBrowsesCompleteFilteredRecordsWithoutExecutingOrFetching(): void
    {
        $project = new ProjectDirectory();
        unlink($project->path('source.html'));
        $project->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'runners' => ['unit' => ['extension' => 'phpunit', 'command' => [PHP_BINARY, '-r', 'file_put_contents("executed.txt", "executed"); exit(1);']]],
        ]);
        $record = [
            'id' => 'ORIGINAL', 'statement' => 'The reader shall preserve positions.', 'origin' => 'original', 'reason' => 'Support editor diagnostics.',
            'labels' => ['diagnostics'], 'category' => 'reader', 'design' => [['text' => 'Preserve offsets.']], 'metadata' => ['owner' => 'parser'],
            'tests' => [['runner' => 'unit', 'target' => 'ReaderTest::testA'], ['runner' => 'unit', 'target' => 'ReaderTest::testB'], ['runner' => 'unit', 'target' => 'ReaderTest::testC']],
        ];
        $project->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [
            $record,
            [...$record, 'id' => 'UNSUPPORTED', 'status' => 'unsupported', 'related' => ['ORIGINAL']],
            [...$record, 'id' => 'UNLINKED', 'tests' => []],
        ]]);
        $process = Cli::run(['spec', '--no-test', '--json'], $project->directory);
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $report = Fields::mapping(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR), 'report');
        self::assertTrue($report['passed']);
        self::assertTrue($report['no_test']);
        $rows = Fields::mapping($report['specifications'], 'specifications');
        self::assertSame(['ORIGINAL' => 'not-run', 'UNSUPPORTED' => 'unsupported', 'UNLINKED' => 'not-run'], array_column($rows, 'status', 'id'));
        self::assertSame(['ORIGINAL' => 3, 'UNSUPPORTED' => 3, 'UNLINKED' => 0], array_column($rows, 'total_targets', 'id'));
        self::assertSame(['ORIGINAL' => null, 'UNSUPPORTED' => null, 'UNLINKED' => null], array_column($rows, 'passed_targets', 'id'));
        self::assertSame(['ORIGINAL' => 0, 'UNSUPPORTED' => 0, 'UNLINKED' => 0], array_column($rows, 'tests', 'id'));
        self::assertSame(['ORIGINAL' => $record['design'], 'UNSUPPORTED' => $record['design'], 'UNLINKED' => $record['design']], array_column($rows, 'design', 'id'));
        self::assertSame(['ORIGINAL' => $record['metadata'], 'UNSUPPORTED' => $record['metadata'], 'UNLINKED' => $record['metadata']], array_column($rows, 'metadata', 'id'));
        self::assertSame(['ORIGINAL' => $record['reason'], 'UNSUPPORTED' => $record['reason'], 'UNLINKED' => $record['reason']], array_column($rows, 'reason', 'id'));
        $row = Fields::mapping($rows['UNSUPPORTED'], 'unsupported');
        self::assertSame(['ORIGINAL'], $row['related']);
        self::assertSame($record['tests'], $row['test_references']);
        $table = Cli::run(['spec', '--no-test', '--without-source', '--label=diagnostics', '--category=reader', '--status=supported', '--origin=original', '--kind=specification', '--id=ORIGINAL', '--no-ansi'], $project->directory);
        self::assertSame(0, $table->getExitCode());
        self::assertStringContainsString('-/3', $table->getOutput());
        self::assertStringContainsString('not-run', $table->getOutput());
        self::assertStringContainsString('diagnostics', $table->getOutput());
        self::assertStringContainsString('Reason: Support editor', $table->getOutput());
        self::assertStringContainsString('tests were not run', $table->getOutput());
        self::assertStringNotContainsString('UNSUPPORTED', $table->getOutput());
        self::assertFileDoesNotExist($project->path('executed.txt'));
        $empty = Cli::run(['spec', '--no-test', '--source=missing'], $project->directory);
        self::assertSame(1, $empty->getExitCode());
    }
}
