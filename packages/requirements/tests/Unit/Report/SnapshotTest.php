<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\CoverageThresholds;
use Requirements\Config\DefinitionReader;
use Requirements\Config\Definitions;
use Requirements\Config\DocumentReader;
use Requirements\Config\ExtensionClasses;
use Requirements\Config\JsonSchemaFile;
use Requirements\Config\LinkValidator;
use Requirements\Config\Loader;
use Requirements\Config\MarkdownDocument;
use Requirements\Config\SchemaValidator;
use Requirements\Ears\ConditionOrder;
use Requirements\Ears\LiteralMask;
use Requirements\Ears\SystemResponse;
use Requirements\Ears\Validator;
use Requirements\Ears\Wording;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Excerpt;
use Requirements\Model\Item;
use Requirements\Model\ItemValidator;
use Requirements\Model\Project;
use Requirements\Model\Source;
use Requirements\Report\Analysis;
use Requirements\Report\Analyzer;
use Requirements\Report\Claims;
use Requirements\Report\Coverage;
use Requirements\Report\EvidenceMatcher;
use Requirements\Report\Snapshot;
use Requirements\Report\SourceUnit;
use Requirements\Report\UnitCollector;
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\LocalFile;
use Requirements\Source\Registry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\TextFragment;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use Requirements\Test\Registry as RunnerRegistry;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Snapshot::class)]
#[UsesClass(Analysis::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Claims::class)]
#[UsesClass(Coverage::class)]
#[UsesClass(EvidenceMatcher::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(UnitCollector::class)]
#[UsesClass(Loader::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Item::class)]
#[UsesClass(Project::class)]
#[UsesClass(Source::class)]
#[UsesClass(Unit::class)]
#[UsesClass(CoverageThresholds::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(Definitions::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(ExtensionClasses::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(LinkValidator::class)]
#[UsesClass(MarkdownDocument::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Validator::class)]
#[UsesClass(Wording::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(ItemValidator::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(Registry::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(RunnerRegistry::class)]
#[Small]
final class SnapshotTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testCreateListsTheFingerprintOfEveryUnit(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'SPEC-001']);
        $claimed = new SourceUnit($source, new Unit('p:1', 'First.'));
        $claimed->claims = ['SPEC-001' => $item];
        $uncovered = new SourceUnit($source, new Unit('p:2', 'Second.'));
        $project = new Project('/', ['SPEC-001' => $item], ['manual' => $source], [], [], [], 0.0, 0.0, [], []);
        $analysis = new Analysis(['a' => $claimed, 'b' => $uncovered], ['manual' => ['a', 'b']], [], ['SPEC-001' => ['a']]);
        self::assertSame(['version' => 1, 'type' => 'requirements-snapshot', 'units' => ['a' => ['fingerprint' => $claimed->fingerprint(['SPEC-001' => $item])], 'b' => ['fingerprint' => $uncovered->fingerprint([])]]], (new Snapshot())->create($analysis, $project));
    }

    /**
     * @throws JsonException
     */
    public function testCreateListsOnlyFingerprintsAndCompareDetectsDrift(): void
    {
        $workspace = new ProjectDirectory();
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        $snapshot = new Snapshot();
        $data = $snapshot->create($analysis, $project);
        self::assertSame('requirements-snapshot', $data['type']);
        self::assertCount(count($analysis->units), $data['units']);
        self::assertSame(array_fill(0, count($data['units']), ['fingerprint']), array_values(array_map(static fn (array $entry): array => array_keys($entry), $data['units'])));
        $file = $workspace->directory . '/snapshot.json';
        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
        self::assertSame(['changed' => [], 'removed' => []], $snapshot->compare($analysis, $project, $snapshot->read($file)));
        file_put_contents($workspace->directory . '/source.html', '<main><p id="a">Changed source.</p></main>');
        self::assertNotEmpty($snapshot->compare((new Analyzer())->analyze($project), $project, $snapshot->read($file))['changed']);
    }

    /**
     * @throws JsonException
     */
    public function testReadReturnsTheFingerprintsByUnitKey(): void
    {
        $workspace = new ProjectDirectory();
        $key = hash('sha256', 'unit');
        $fingerprint = hash('sha256', 'fingerprint');
        $file = $workspace->put('snapshot.json', json_encode(['version' => 1, 'type' => 'requirements-snapshot', 'units' => [$key => ['fingerprint' => $fingerprint]]], JSON_THROW_ON_ERROR));
        self::assertSame([$key => $fingerprint], (new Snapshot())->read($file));
    }

    /**
     * @throws JsonException
     */
    public function testReadAcceptsASnapshotWithoutUnits(): void
    {
        $workspace = new ProjectDirectory();
        $file = $workspace->put('snapshot.json', '{"version":1,"type":"requirements-snapshot","units":{}}');
        self::assertSame([], (new Snapshot())->read($file));
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsAUnitWithoutFingerprint(): void
    {
        $workspace = new ProjectDirectory();
        file_put_contents($workspace->directory . '/bad.json', '{"version":1,"type":"requirements-snapshot","units":{"x":{}}}');
        $this->expectException(InvalidInputException::class);
        (new Snapshot())->read($workspace->directory . '/bad.json');
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsACoverageReport(): void
    {
        $workspace = new ProjectDirectory();
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $file = $workspace->directory . '/coverage.json';
        file_put_contents($file, json_encode((new Coverage())->report($project, (new Analyzer())->analyze($project)), JSON_THROW_ON_ERROR));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('--write-snapshot');
        (new Snapshot())->read($file);
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerUnreadableFiles')]
    public function testReadRejectsAFileThatCannotBeRead(string $file): void
    {
        $workspace = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Cannot read coverage snapshot: ' . $workspace->path($file));
        (new Snapshot())->read($workspace->path($file));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnreadableFiles(): array
    {
        return [
            'missing' => ['missing.json'],
            'directory' => [''],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsAFileThatIsNotJson(): void
    {
        $workspace = new ProjectDirectory();
        $file = $workspace->put('snapshot.json', '{"version":');
        $this->expectException(JsonException::class);
        (new Snapshot())->read($file);
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerMalformedSnapshots')]
    public function testReadRejectsAMalformedSnapshot(string $contents, string $message): void
    {
        $workspace = new ProjectDirectory();
        $file = $workspace->put('snapshot.json', $contents);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Snapshot())->read($file);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedSnapshots(): array
    {
        $key = hash('sha256', 'unit');
        $fingerprint = hash('sha256', 'fingerprint');
        return [
            'not a mapping' => ['"snapshot"', 'snapshot must be a mapping.'],
            'no version' => ['{"type":"requirements-snapshot","units":{}}', 'Unsupported coverage snapshot. Write one with coverage --write-snapshot.'],
            'other version' => ['{"version":2,"type":"requirements-snapshot","units":{}}', 'Unsupported coverage snapshot. Write one with coverage --write-snapshot.'],
            'version as text' => ['{"version":"1","type":"requirements-snapshot","units":{}}', 'Unsupported coverage snapshot. Write one with coverage --write-snapshot.'],
            'no type' => ['{"version":1,"units":{}}', 'Unsupported coverage snapshot. Write one with coverage --write-snapshot.'],
            'coverage type' => ['{"version":1,"type":"requirements-coverage","units":{}}', 'Unsupported coverage snapshot. Write one with coverage --write-snapshot.'],
            'no units' => ['{"version":1,"type":"requirements-snapshot"}', 'snapshot.units must be a mapping.'],
            'unit not a mapping' => ['{"version":1,"type":"requirements-snapshot","units":{"' . $key . '":"' . $fingerprint . '"}}', 'snapshot.unit must be a mapping.'],
            'short key' => ['{"version":1,"type":"requirements-snapshot","units":{"' . substr($key, 1) . '":{"fingerprint":"' . $fingerprint . '"}}}', 'Invalid coverage snapshot unit.'],
            'uppercase key' => ['{"version":1,"type":"requirements-snapshot","units":{"' . strtoupper($key) . '":{"fingerprint":"' . $fingerprint . '"}}}', 'Invalid coverage snapshot unit.'],
            'key with trailing newline' => ['{"version":1,"type":"requirements-snapshot","units":{"' . $key . '\n":{"fingerprint":"' . $fingerprint . '"}}}', 'Invalid coverage snapshot unit.'],
            'key with prefix' => ['{"version":1,"type":"requirements-snapshot","units":{"x' . $key . '":{"fingerprint":"' . $fingerprint . '"}}}', 'Invalid coverage snapshot unit.'],
            'short fingerprint' => ['{"version":1,"type":"requirements-snapshot","units":{"' . $key . '":{"fingerprint":"' . substr($fingerprint, 1) . '"}}}', 'Invalid coverage snapshot unit.'],
            'fingerprint with trailing newline' => ['{"version":1,"type":"requirements-snapshot","units":{"' . $key . '":{"fingerprint":"' . $fingerprint . '\n"}}}', 'Invalid coverage snapshot unit.'],
            'fingerprint with prefix' => ['{"version":1,"type":"requirements-snapshot","units":{"' . $key . '":{"fingerprint":"x' . $fingerprint . '"}}}', 'Invalid coverage snapshot unit.'],
            'blank fingerprint' => ['{"version":1,"type":"requirements-snapshot","units":{"' . $key . '":{"fingerprint":""}}}', 'fingerprint must be a nonempty string.'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testCompareListsNewAndChangedUnitsAndRemovedKeys(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $same = new SourceUnit($source, new Unit('p:1', 'First.'));
        $changed = new SourceUnit($source, new Unit('p:2', 'Second.'));
        $added = new SourceUnit($source, new Unit('p:3', 'Third.'));
        $project = new Project('/', [], ['manual' => $source], [], [], [], 0.0, 0.0, [], []);
        $analysis = new Analysis(['a' => $same, 'b' => $changed, 'c' => $added], ['manual' => ['a', 'b', 'c']], [], []);
        $snapshot = ['r' => hash('sha256', 'removed'), 'a' => $same->fingerprint([]), 'b' => hash('sha256', 'before'), 's' => hash('sha256', 'also removed')];
        self::assertSame(['changed' => ['b', 'c'], 'removed' => ['r', 's']], (new Snapshot())->compare($analysis, $project, $snapshot));
    }

    /**
     * @throws JsonException
     */
    public function testCompareDetectsChangedClaimsNewUnitsAndRemovedScope(): void
    {
        $workspace = new ProjectDirectory();
        $loader = new Loader();
        $project = $loader->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        $file = $workspace->directory . '/snapshot.json';
        file_put_contents($file, json_encode((new Snapshot())->create($analysis, $project), JSON_THROW_ON_ERROR));
        $snapshot = (new Snapshot())->read($file);
        self::assertSame(['changed' => [], 'removed' => []], (new Snapshot())->compare($analysis, $project, $snapshot));
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $item = ProjectDirectory::item();
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
}
