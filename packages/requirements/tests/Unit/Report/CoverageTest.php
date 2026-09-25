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

#[CoversClass(Coverage::class)]
#[UsesClass(Analysis::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Claims::class)]
#[UsesClass(EvidenceMatcher::class)]
#[UsesClass(Snapshot::class)]
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
final class CoverageTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testReportDescribesEveryUnitAndPassesWithoutGates(): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', ['id' => 'SPEC-001']);
        $claimed = new SourceUnit($source, new Unit('p:1', 'First.'));
        $claimed->claims = ['SPEC-001' => $item];
        $uncovered = new SourceUnit($source, new Unit('p:2', 'Second.'));
        $project = new Project('/', ['SPEC-001' => $item], ['manual' => $source], [], [], [], 0.0, 0.0, [], []);
        $analysis = new Analysis(['a' => $claimed, 'b' => $uncovered], ['manual' => ['a', 'b'], 'empty' => []], [], ['SPEC-001' => ['a']]);
        self::assertSame([
            'version' => 1,
            'type' => 'requirements-coverage',
            'overall' => ['total' => 2, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 1, 'percentage' => 50.0],
            'sources' => [
                'manual' => ['total' => 2, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 1, 'percentage' => 50.0],
                'empty' => ['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null],
            ],
            'diff' => null,
            'removed' => [],
            'units' => ['a' => $claimed->toArray(['SPEC-001' => $item]), 'b' => $uncovered->toArray([])],
            'errors' => [],
            'passed' => true,
        ], (new Coverage())->report($project, $analysis));
    }

    /**
     * @throws JsonException
     */
    public function testReportFailsWithTheErrorsOfTheAnalysis(): void
    {
        $project = new Project('/', [], [], [], [], [], 0.0, 0.0, [], []);
        $report = (new Coverage())->report($project, new Analysis([], ['manual' => []], ['manual: Scope selected no source units.'], []));
        self::assertSame(['manual: Scope selected no source units.'], $report['errors']);
        self::assertFalse($report['passed']);
    }

    /**
     * @param list<string> $errors
     * @throws JsonException
     */
    #[DataProvider('providerOverallThresholds')]
    public function testReportAppliesTheOverallThreshold(float $configured, ?float $override, array $errors): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $claimed = new SourceUnit($source, new Unit('p:1', 'First.'));
        $claimed->claims = ['SPEC-001' => $item];
        $project = new Project('/', ['SPEC-001' => $item], ['manual' => $source], [], [], [], $configured, 0.0, [], []);
        $analysis = new Analysis(['a' => $claimed, 'b' => new SourceUnit($source, new Unit('p:2', 'Second.'))], ['manual' => ['a', 'b']], [], []);
        $report = (new Coverage())->report($project, $analysis, minimum: $override);
        self::assertSame($errors, $report['errors']);
        self::assertSame($errors === [], $report['passed']);
    }

    /**
     * @return array<string, array{float, float|null, list<string>}>
     */
    public static function providerOverallThresholds(): array
    {
        return [
            'configured below' => [40.0, null, []],
            'configured equal' => [50.0, null, []],
            'configured above' => [50.5, null, ['Overall source coverage is below 50.5%.']],
            'override below configured' => [80.0, 50.0, []],
            'override above configured' => [0.0, 80.0, ['Overall source coverage is below 80%.']],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testReportFailsAPositiveOverallThresholdWithoutUnits(): void
    {
        $project = new Project('/', [], [], [], [], [], 0.0, 0.0, [], []);
        $report = (new Coverage())->report($project, new Analysis([], [], [], []), minimum: 0.5);
        self::assertSame(['Overall source coverage is below 0.5%.'], $report['errors']);
    }

    /**
     * @param array<string, float> $thresholds
     * @param list<string> $errors
     * @throws JsonException
     */
    #[DataProvider('providerSourceThresholds')]
    public function testReportAppliesTheSourceThresholds(array $thresholds, array $errors): void
    {
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $claimed = new SourceUnit($source, new Unit('p:1', 'First.'));
        $claimed->claims = ['SPEC-001' => $item];
        $project = new Project('/', ['SPEC-001' => $item], ['manual' => $source], [], [], [], 0.0, 0.0, $thresholds, []);
        $analysis = new Analysis(['a' => $claimed, 'b' => new SourceUnit($source, new Unit('p:2', 'Second.'))], ['manual' => ['a', 'b'], 'full' => ['a'], 'empty' => []], [], []);
        self::assertSame($errors, (new Coverage())->report($project, $analysis)['errors']);
    }

    /**
     * @return array<string, array{array<string, float>, list<string>}>
     */
    public static function providerSourceThresholds(): array
    {
        return [
            'none' => [[], []],
            'equal' => [['manual' => 50.0], []],
            'above' => [['manual' => 60.0, 'full' => 100.0], ['manual: source coverage is below 60%.']],
            'empty scope' => [['empty' => 10.0], ['empty: source coverage is below 10%.']],
        ];
    }

    /**
     * @param list<string> $errors
     * @throws JsonException
     */
    #[DataProvider('providerDifferentialThresholdsWithoutSnapshot')]
    public function testReportRequiresASnapshotForAPositiveDifferentialThreshold(float $configured, ?float $override, array $errors): void
    {
        $project = new Project('/', [], [], [], [], [], 0.0, $configured, [], []);
        $report = (new Coverage())->report($project, new Analysis([], [], [], []), diffMinimum: $override);
        self::assertSame($errors, $report['errors']);
        self::assertNull($report['diff']);
    }

    /**
     * @return array<string, array{float, float|null, list<string>}>
     */
    public static function providerDifferentialThresholdsWithoutSnapshot(): array
    {
        return [
            'zero' => [0.0, null, []],
            'configured' => [0.5, null, ['A positive differential threshold requires --snapshot.']],
            'override' => [0.0, 0.5, ['A positive differential threshold requires --snapshot.']],
            'override to zero' => [50.0, 0.0, []],
        ];
    }

    /**
     * @param array{total: int, accounted: int, supported: int, unsupported: int, uncovered: int, percentage: float|null} $diff
     * @param list<string> $errors
     * @throws JsonException
     */
    #[DataProvider('providerDifferentialThresholds')]
    public function testReportAppliesTheDifferentialThresholdToNewAndChangedUnits(bool $claimNew, float $configured, ?float $override, array $diff, array $errors): void
    {
        $workspace = new ProjectDirectory();
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $item = new Item('SPEC-001', 'specification', 'The parser shall read names.', 'supported', $source, [], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $old = new SourceUnit($source, new Unit('p:1', 'First.'));
        $new = new SourceUnit($source, new Unit('p:2', 'Second.'));
        $new->claims = $claimNew ? ['SPEC-001' => $item] : [];
        $oldKey = $old->unit->key($source);
        $newKey = $new->unit->key($source);
        $file = $workspace->put('snapshot.json', json_encode(['version' => 1, 'type' => 'requirements-snapshot', 'units' => [$oldKey => ['fingerprint' => $old->fingerprint([])]]], JSON_THROW_ON_ERROR));
        $project = new Project('/', ['SPEC-001' => $item], ['manual' => $source], [], [], [], 0.0, $configured, [], []);
        $analysis = new Analysis([$oldKey => $old, $newKey => $new], ['manual' => [$oldKey, $newKey]], [], []);
        $report = (new Coverage())->report($project, $analysis, $file, diffMinimum: $override);
        self::assertSame($diff, $report['diff']);
        self::assertSame([], $report['removed']);
        self::assertSame($errors, $report['errors']);
    }

    /**
     * @return array<string, array{bool, float, float|null, array{total: int, accounted: int, supported: int, unsupported: int, uncovered: int, percentage: float|null}, list<string>}>
     */
    public static function providerDifferentialThresholds(): array
    {
        $uncovered = ['total' => 1, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 1, 'percentage' => 0.0];
        $covered = ['total' => 1, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => 100.0];
        return [
            'no threshold' => [false, 0.0, null, $uncovered, []],
            'configured' => [false, 50.0, null, $uncovered, ['Differential source coverage is below 50%.']],
            'override' => [false, 0.0, 50.0, $uncovered, ['Differential source coverage is below 50%.']],
            'override to zero' => [false, 50.0, 0.0, $uncovered, []],
            'covered' => [true, 100.0, null, $covered, []],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testReportSkipsTheDifferentialThresholdWithoutChangedUnits(): void
    {
        $workspace = new ProjectDirectory();
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $unit = new SourceUnit($source, new Unit('p:1', 'First.'));
        $key = $unit->unit->key($source);
        $file = $workspace->put('snapshot.json', json_encode(['version' => 1, 'type' => 'requirements-snapshot', 'units' => [$key => ['fingerprint' => $unit->fingerprint([])]]], JSON_THROW_ON_ERROR));
        $project = new Project('/', [], ['manual' => $source], [], [], [], 0.0, 100.0, [], []);
        $report = (new Coverage())->report($project, new Analysis([$key => $unit], ['manual' => [$key]], [], []), $file);
        self::assertSame(['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null], $report['diff']);
        self::assertSame([], $report['errors']);
        self::assertTrue($report['passed']);
    }

    /**
     * @param list<string> $errors
     * @throws JsonException
     */
    #[DataProvider('providerRemovedUnits')]
    public function testReportRejectsRemovedUnitsUnlessAllowed(bool $allowRemoved, array $errors): void
    {
        $workspace = new ProjectDirectory();
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $unit = new SourceUnit($source, new Unit('p:1', 'First.'));
        $key = $unit->unit->key($source);
        $removed = hash('sha256', 'removed');
        $file = $workspace->put('snapshot.json', json_encode(['version' => 1, 'type' => 'requirements-snapshot', 'units' => [$key => ['fingerprint' => $unit->fingerprint([])], $removed => ['fingerprint' => $removed]]], JSON_THROW_ON_ERROR));
        $project = new Project('/', [], ['manual' => $source], [], [], [], 0.0, 0.0, [], []);
        $report = (new Coverage())->report($project, new Analysis([$key => $unit], ['manual' => [$key]], [], []), $file, $allowRemoved);
        self::assertSame([$removed], $report['removed']);
        self::assertSame($errors, $report['errors']);
        self::assertSame($errors === [], $report['passed']);
    }

    /**
     * @return array<string, array{bool, list<string>}>
     */
    public static function providerRemovedUnits(): array
    {
        return [
            'rejected' => [false, ['Source units listed in the snapshot were removed. Review scope changes before using --allow-removed.']],
            'allowed' => [true, []],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testReportRejectsAFileThatIsNotASnapshot(): void
    {
        $workspace = new ProjectDirectory();
        $file = $workspace->put('coverage.json', '{"version":1,"type":"requirements-coverage"}');
        $project = new Project('/', [], [], [], [], [], 0.0, 0.0, [], []);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('--write-snapshot');
        (new Coverage())->report($project, new Analysis([], [], [], []), $file);
    }

    /**
     * @throws JsonException
     */
    public function testReportFailsTheDifferentialGateForANewUncoveredUnit(): void
    {
        $workspace = new ProjectDirectory();
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
}
