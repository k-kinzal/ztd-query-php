<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Loader;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Report\Analyzer;
use Requirements\Report\Coverage;
use Requirements\Report\Snapshot;
use Requirements\Source\DomSource;
use Requirements\Source\ResourceLoader;
use Requirements\Test\JUnit;
use Requirements\Test\ProcessRunner;
use Requirements\Test\RunnerConfig;
use RuntimeException;
use Tests\Support\Workspace;

final class FailureTest extends TestCase
{
    #[DataProvider('reports')]
    public function testJUnitCannotPassWithoutExecutedPassingCases(string $xml, string $status): void
    {
        $workspace = new Workspace();
        $file = $workspace->directory . '/report.xml';
        file_put_contents($file, $xml);
        self::assertSame($status, (new JUnit())->read([$file], 0, '')->status);
    }

    /** @return list<array{string, string}> */
    public static function reports(): array
    {
        return [
            ['<testsuite/>', 'error'],
            ['<testsuite><testcase/></testsuite>', 'passed'],
            ['<testsuite><testcase><skipped/></testcase></testsuite>', 'failed'],
            ['<testsuite><testcase status="pending"/></testsuite>', 'failed'],
            ['<testsuite><testcase><error/></testcase></testsuite>', 'failed'],
            ['<testsuite><testcase>', 'error'],
            ['<!DOCTYPE a><testsuite><testcase/></testsuite>', 'error'],
        ];
    }

    public function testProcessTimeoutAndMissingReportFail(): void
    {
        $workspace = new Workspace();
        $runner = new ProcessRunner();
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'usleep(500000);'], $workspace->directory, 0.05);
        self::assertSame('error', $runner->run($config, static fn (string $directory): array => [])->status);
        $config = new RunnerConfig('custom', [PHP_BINARY, '-r', 'exit(0);'], $workspace->directory);
        self::assertSame('error', $runner->run($config, static fn (string $directory): array => [])->status);
    }

    public function testLiveModeBypassesPinnedSnapshotAndDetectsCurrentText(): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/current.txt', 'New text.');
        file_put_contents($workspace->directory . '/snapshot.txt', 'Old text.');
        $source = new Source('test', 'current.txt', 'text', 'lines:1', 'snapshot.txt', hash('sha256', 'Old text.'));
        $loader = new ResourceLoader();
        self::assertSame('Old text.', $loader->read($source, $workspace->directory, false));
        self::assertSame('New text.', $loader->read($source, $workspace->directory, true));
    }

    public function testXmlEntitiesAreRejected(): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/unsafe.xml', '<!DOCTYPE x [<!ENTITY secret SYSTEM "file:///etc/passwd">]><x>&secret;</x>');
        $source = new Source('test', 'unsafe.xml', 'xml', 'x');
        $this->expectException(RuntimeException::class);
        (new DomSource())->select($source, 'x', $workspace->directory, false);
    }

    public function testNewUncoveredUnitFailsDifferentialGate(): void
    {
        $workspace = new Workspace();
        $loader = new Loader();
        $project = $loader->load($workspace->directory . '/requirements.yaml');
        $snapshot = $workspace->directory . '/snapshot.json';
        file_put_contents($snapshot, json_encode((new Snapshot())->create((new Analyzer())->analyze($project), $project), JSON_THROW_ON_ERROR));
        file_put_contents($workspace->directory . '/source.html', '<main><p id="a">Names shall start with a letter.</p><p id="b">Names may contain digits.</p><p id="c">The generator shall produce C code.</p><p id="d">New unreviewed rule.</p></main>');
        $report = (new Coverage())->report($project, (new Analyzer())->analyze($project), $snapshot, diffMinimum: 100);
        self::assertFalse($report['passed']);
        self::assertIsArray($report['diff']);
        self::assertSame(0.0, $report['diff']['percentage']);
    }

    public function testMalformedSnapshotIsRejected(): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/bad.json', '{"version":1,"type":"requirements-snapshot","units":{"x":{}}}');
        $this->expectException(InvalidInputException::class);
        (new Snapshot())->read($workspace->directory . '/bad.json');
    }

    public function testCoverageReportIsNotAcceptedAsSnapshot(): void
    {
        $workspace = new Workspace();
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $file = $workspace->directory . '/coverage.json';
        file_put_contents($file, json_encode((new Coverage())->report($project, (new Analyzer())->analyze($project)), JSON_THROW_ON_ERROR));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('--write-snapshot');
        (new Snapshot())->read($file);
    }

    public function testMissingSnapshotIsFetchedAndVerifiedBeforeCaching(): void
    {
        $workspace = new Workspace();
        $content = 'Original source text.';
        file_put_contents($workspace->directory . '/upstream.txt', $content);
        $source = new Source('test', 'upstream.txt', 'text', 'lines:1', '.requirements-cache/manual.txt', hash('sha256', $content));
        self::assertSame($content, (new ResourceLoader())->read($source, $workspace->directory, false));
        unlink($workspace->directory . '/upstream.txt');
        self::assertSame($content, (new ResourceLoader())->read($source, $workspace->directory, false));
        file_put_contents($workspace->directory . '/.requirements-cache/manual.txt', 'Changed text.');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHA-256');
        (new ResourceLoader())->read($source, $workspace->directory, false);
    }

    public function testUnverifiedDownloadIsNeverCached(): void
    {
        $workspace = new Workspace();
        file_put_contents($workspace->directory . '/upstream.txt', 'Changed text.');
        $source = new Source('test', 'upstream.txt', 'text', 'lines:1', '.requirements-cache/manual.txt', hash('sha256', 'Original text.'));
        try {
            (new ResourceLoader())->read($source, $workspace->directory, false);
            self::fail('Expected digest verification failure.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('SHA-256', $error->getMessage());
            self::assertFileDoesNotExist($workspace->directory . '/.requirements-cache/manual.txt');
        }
    }

    public function testSnapshotContainsOnlyFingerprintsAndDetectsDrift(): void
    {
        $workspace = new Workspace();
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        $snapshot = new Snapshot();
        $data = $snapshot->create($analysis, $project);
        self::assertSame('requirements-snapshot', $data['type']);
        self::assertCount(count($analysis->units), $data['units']);
        foreach ($data['units'] as $entry) {
            self::assertSame(['fingerprint'], array_keys($entry));
        }
        $file = $workspace->directory . '/snapshot.json';
        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
        self::assertSame(['changed' => [], 'removed' => []], $snapshot->compare($analysis, $project, $snapshot->read($file)));
        file_put_contents($workspace->directory . '/source.html', '<main><p id="a">Changed source.</p></main>');
        self::assertNotEmpty($snapshot->compare((new Analyzer())->analyze($project), $project, $snapshot->read($file))['changed']);
    }

}
