<?php

declare(strict_types=1);

namespace Requirements\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Requirements\Config\Fields;
use Requirements\Tests\Support\Workspace;
use Symfony\Component\Process\Process;

final class SpecTest extends TestCase
{
    public function testCountsPassingTargetsPerSpecificationSeparatelyFromDataSets(): void
    {
        $workspace = new Workspace();
        $package = dirname(__DIR__, 2);
        $workspace->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'runners' => ['unit' => ['extension' => 'phpunit', 'command' => [PHP_BINARY, $package . '/vendor/bin/phpunit', '--no-configuration', $package . '/tests/Fixtures/PassingTest.php']]],
        ]);
        $targets = [
            'PASS' => ['testPass', 'testData'],
            'PARTIAL' => ['testPass', 'testFailure', 'testSkip'],
            'MISSING' => ['testMissing'],
            'SHARED' => ['testData', 'testData'],
            'UNVERIFIED' => [],
            'UNSUPPORTED' => ['testFailure'],
            'REQ' => [],
        ];
        $items = [];
        foreach ($targets as $id => $methods) {
            $items[] = [...Workspace::item(), 'id' => $id, 'kind' => $id === 'REQ' ? 'requirement' : 'specification', 'status' => $id === 'UNSUPPORTED' ? 'unsupported' : 'supported', 'reason' => 'A documented decision.', 'tests' => array_map(static fn (string $method): array => ['runner' => 'unit', 'target' => 'Requirements\\Tests\\Fixtures\\PassingTest::' . $method], $methods)];
        }
        $workspace->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => $items]);
        $process = new Process([PHP_BINARY, $package . '/bin/requirements', 'spec', '--json'], $workspace->directory);
        self::assertSame(1, $process->run(), $process->getErrorOutput());
        $report = Fields::mapping(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR), 'report');
        self::assertFalse($report['passed']);
        self::assertFalse($report['no_test']);
        $rows = Fields::mapping($report['specifications'], 'specifications');
        foreach ([
            'PASS' => ['passed', 2, 2, 3],
            'PARTIAL' => ['failed', 1, 3, 3],
            'MISSING' => ['failed', 0, 1, 0],
            'SHARED' => ['passed', 1, 1, 2],
            'UNVERIFIED' => ['unverified', 0, 0, 0],
            'UNSUPPORTED' => ['unsupported', null, 1, 0],
            'REQ' => ['not-applicable', null, 0, 0],
        ] as $id => $expected) {
            $row = Fields::mapping($rows[$id], $id);
            self::assertSame($expected, [$row['status'], $row['passed_targets'], $row['total_targets'], $row['tests']], $id);
            self::assertSame('manual', $row['source']);
            self::assertSame($id === 'UNSUPPORTED' ? 'unsupported' : 'supported', $row['support']);
        }
        $table = new Process([PHP_BINARY, $package . '/bin/requirements', 'spec', '--id=PARTIAL', '--no-ansi'], $workspace->directory);
        self::assertSame(1, $table->run());
        self::assertStringContainsString('1/3', $table->getOutput());
        self::assertStringNotContainsString('2/2', $table->getOutput());
        $passing = new Process([PHP_BINARY, $package . '/bin/requirements', 'spec', '--id=PASS', '--no-ansi'], $workspace->directory);
        self::assertSame(0, $passing->run());
        self::assertStringContainsString('2/2', $passing->getOutput());
        $requirements = new Process([PHP_BINARY, $package . '/bin/requirements', 'spec', '--kind=requirement', '--no-ansi'], $workspace->directory);
        self::assertSame(0, $requirements->run());
        self::assertStringContainsString('REQ', $requirements->getOutput());
        self::assertStringContainsString('-/0', $requirements->getOutput());
    }

    public function testNoTestBrowsesCompleteFilteredRecordsWithoutExecutingOrFetching(): void
    {
        $workspace = new Workspace();
        unlink($workspace->directory . '/source.html');
        $workspace->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'runners' => ['unit' => ['extension' => 'phpunit', 'command' => [PHP_BINARY, '-r', 'file_put_contents("executed.txt", "executed"); exit(1);']]],
        ]);
        $record = [
            'id' => 'ORIGINAL', 'statement' => 'The reader shall preserve positions.', 'origin' => 'original', 'reason' => 'Support editor diagnostics.',
            'labels' => ['diagnostics'], 'category' => 'reader', 'design' => [['text' => 'Preserve offsets.']], 'metadata' => ['owner' => 'parser'],
            'tests' => [['runner' => 'unit', 'target' => 'ReaderTest::testA'], ['runner' => 'unit', 'target' => 'ReaderTest::testB'], ['runner' => 'unit', 'target' => 'ReaderTest::testC']],
        ];
        $workspace->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [
            $record,
            [...$record, 'id' => 'UNSUPPORTED', 'status' => 'unsupported', 'related' => ['ORIGINAL']],
            [...$record, 'id' => 'UNLINKED', 'tests' => []],
        ]]);
        $binary = dirname(__DIR__, 2) . '/bin/requirements';
        $process = new Process([PHP_BINARY, $binary, 'spec', '--no-test', '--json'], $workspace->directory);
        self::assertSame(0, $process->run(), $process->getErrorOutput());
        $report = Fields::mapping(json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR), 'report');
        self::assertTrue($report['passed']);
        self::assertTrue($report['no_test']);
        $rows = Fields::mapping($report['specifications'], 'specifications');
        foreach (['ORIGINAL' => ['not-run', 3], 'UNSUPPORTED' => ['unsupported', 3], 'UNLINKED' => ['not-run', 0]] as $id => $expected) {
            $row = Fields::mapping($rows[$id], $id);
            self::assertSame($expected, [$row['status'], $row['total_targets']]);
            self::assertNull($row['passed_targets']);
            self::assertSame(0, $row['tests']);
            self::assertSame($record['design'], $row['design']);
            self::assertSame($record['metadata'], $row['metadata']);
            self::assertSame($record['reason'], $row['reason']);
        }
        $row = Fields::mapping($rows['UNSUPPORTED'], 'unsupported');
        self::assertSame(['ORIGINAL'], $row['related']);
        self::assertSame($record['tests'], $row['test_references']);
        $table = new Process([PHP_BINARY, $binary, 'spec', '--no-test', '--without-source', '--label=diagnostics', '--category=reader', '--status=supported', '--origin=original', '--kind=specification', '--id=ORIGINAL', '--no-ansi'], $workspace->directory);
        self::assertSame(0, $table->run());
        self::assertStringContainsString('-/3', $table->getOutput());
        self::assertStringContainsString('not-run', $table->getOutput());
        self::assertStringContainsString('diagnostics', $table->getOutput());
        self::assertStringContainsString('Reason: Support editor', $table->getOutput());
        self::assertStringContainsString('tests were not run', $table->getOutput());
        self::assertStringNotContainsString('UNSUPPORTED', $table->getOutput());
        self::assertFileDoesNotExist($workspace->directory . '/executed.txt');
        $empty = new Process([PHP_BINARY, $binary, 'spec', '--no-test', '--source=missing'], $workspace->directory);
        self::assertSame(1, $empty->run());
    }
}
