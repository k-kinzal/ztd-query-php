<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Loader;
use Requirements\Input\InvalidInputException;
use Requirements\Report\Analyzer;
use Requirements\Report\Coverage;
use Requirements\Report\Snapshot;
use Tests\Support\Workspace;

final class TraceabilityTest extends TestCase
{
    public function testCoverageUsesIndependentScopeAndReasonedDisposition(): void
    {
        $workspace = new Workspace();
        $loader = new Loader();
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $unsupported = Workspace::item();
        $unsupported['id'] = 'UNSUPPORTED-001';
        $unsupported['status'] = 'unsupported';
        $unsupported['reason'] = 'This library reads grammars; it does not generate C.';
        $unsupported['statement'] = 'The generator shall produce C code.';
        $unsupported['evidence'] = [['selector' => '#c', 'quote' => 'The generator shall produce C code.']];
        $definition['items'] = [Workspace::item(), $unsupported];
        $workspace->write('definition.yaml', $definition);
        $project = $loader->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        self::assertSame([], $analysis->errors);
        self::assertSame(['total' => 3, 'accounted' => 2, 'supported' => 1, 'unsupported' => 1, 'uncovered' => 1, 'percentage' => 200.0 / 3], $analysis->summary());
        self::assertFalse((new Coverage())->report($project, $analysis, minimum: 80)['passed']);
    }

    #[DataProvider('invalidEvidence')]
    public function testEvidenceFailsClosed(string $selector, string $quote, string $error): void
    {
        $workspace = new Workspace();
        $loader = new Loader();
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $item = Workspace::item();
        $item['evidence'] = [['selector' => $selector, 'quote' => $quote]];
        $definition['items'] = [$item];
        $workspace->write('definition.yaml', $definition);
        $analysis = (new Analyzer())->analyze($loader->load($workspace->directory . '/requirements.yaml'));
        self::assertStringContainsString($error, implode(' ', $analysis->errors));
        self::assertSame(0, $analysis->summary()['accounted']);
    }

    /** @return list<array{string, string, string}> */
    public static function invalidEvidence(): array
    {
        return [
            ['#missing', 'Nothing.', 'exactly one'],
            ['main p', 'Names shall start with a letter.', 'exactly one'],
            ['#a', 'start with a letter', 'differs'],
            ['#outside', 'Outside scope.', 'outside'],
        ];
    }

    public function testEmptyScopeIsAnErrorAndNotFullCoverage(): void
    {
        $workspace = new Workspace();
        $definition = (new Loader())->document($workspace->directory . '/definition.yaml');
        $definition['source'] = ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#missing'];
        $definition['items'] = [];
        $workspace->write('definition.yaml', $definition);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertNotEmpty($analysis->errors);
        self::assertNull($analysis->summary()['percentage']);
    }

    public function testRequirementNeedsSpecificationAndCanBeSharedAcrossDefinitions(): void
    {
        $workspace = new Workspace();
        $loader = new Loader();
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $requirement = Workspace::item();
        $requirement['kind'] = 'requirement';
        $requirement['id'] = 'REQ-001';
        $definition['items'] = [$requirement];
        $workspace->write('definition.yaml', $definition);
        self::assertSame(0, (new Analyzer())->analyze($loader->load($workspace->directory . '/requirements.yaml'))->summary()['accounted']);
        $specification = ['id' => 'SPEC-001', 'statement' => 'The parser shall require a leading letter.', 'requirements' => ['REQ-001']];
        $workspace->write('other.yaml', ['version' => 1, 'source' => null, 'items' => [$specification]]);
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml', 'other.yaml']]);
        $analysis = (new Analyzer())->analyze($loader->load($workspace->directory . '/requirements.yaml'));
        self::assertSame([], $analysis->errors);
        self::assertSame(1, $analysis->summary()['accounted']);
    }

    public function testDuplicateScopeUnitsCountOnceGlobally(): void
    {
        $workspace = new Workspace();
        $definition = (new Loader())->document($workspace->directory . '/definition.yaml');
        $definition['source'] = ['id' => 'overlap', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#a'];
        $definition['items'] = [];
        $workspace->write('other.yaml', $definition);
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml', 'other.yaml']]);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertSame(3, $analysis->summary()['total']);
        self::assertSame(100.0, $analysis->summary($analysis->scopes['overlap'])['percentage']);
    }

    public function testSnapshotDetectsChangedClaimsNewUnitsAndRemovedScope(): void
    {
        $workspace = new Workspace();
        $loader = new Loader();
        $project = $loader->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        $file = $workspace->directory . '/snapshot.json';
        file_put_contents($file, json_encode((new Snapshot())->create($analysis, $project), JSON_THROW_ON_ERROR));
        $snapshot = (new Snapshot())->read($file);
        self::assertSame(['changed' => [], 'removed' => []], (new Snapshot())->compare($analysis, $project, $snapshot));
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $item = Workspace::item();
        $item['statement'] = 'The parser shall reject names beginning with digits.';
        $definition['items'] = [$item];
        $workspace->write('definition.yaml', $definition);
        $changed = $loader->load($workspace->directory . '/requirements.yaml');
        self::assertCount(1, (new Snapshot())->compare((new Analyzer())->analyze($changed), $changed, $snapshot)['changed']);
        $definition['source'] = ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#a'];
        $workspace->write('definition.yaml', $definition);
        $reduced = $loader->load($workspace->directory . '/requirements.yaml');
        $report = (new Coverage())->report($reduced, (new Analyzer())->analyze($reduced), $file);
        self::assertFalse($report['passed']);
        self::assertIsArray($report['removed']);
        self::assertCount(2, $report['removed']);
        self::assertTrue((new Coverage())->report($reduced, (new Analyzer())->analyze($reduced), $file, true)['passed']);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidItems')]
    public function testRejectsInvalidDefinitions(array $changes, string $message): void
    {
        $workspace = new Workspace();
        $definition = (new Loader())->document($workspace->directory . '/definition.yaml');
        $definition['items'] = [array_replace(Workspace::item(), $changes)];
        $workspace->write('definition.yaml', $definition);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Loader())->load($workspace->directory . '/requirements.yaml');
    }

    /** @return list<array{array<string, mixed>, string}> */
    public static function invalidItems(): array
    {
        return [
            [['status' => 'unsupported'], 'reason'],
            [['statement' => 'Maybe names are letters'], 'EARS'],
            [['requirements' => ['MISSING']], 'reference'],
            [['related' => ['SPEC-001']], 'reference'],
            [['lables' => ['typo']], 'Additional object properties'],
            [['tests' => [['runner' => 'missing', 'target' => 'Test::test']]], 'unknown runner'],
            [['labels' => ['same', 'same']], 'unique items'],
        ];
    }
}
