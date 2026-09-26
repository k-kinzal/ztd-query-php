<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

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
use Requirements\Console\Executor;
use Requirements\Console\Formatter;
use Requirements\Console\ItemRecord;
use Requirements\Console\Options;
use Requirements\Console\SpecificationReport;
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
use Requirements\Model\TestReference;
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
use Requirements\Source\Registry as SourceRegistry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\TextFragment;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use Requirements\Test\Registry as TestRegistry;
use Requirements\Test\RunnerConfig;
use Requirements\Test\TestResult;
use Requirements\Verification\TargetResults;
use Requirements\Verification\TestExecution;
use Requirements\Verification\VerificationResult;
use Requirements\Verification\Verifier;
use Tests\Fake\CountingRunner;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Executor::class)]
#[UsesClass(SpecificationReport::class)]
#[UsesClass(CoverageThresholds::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(Definitions::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(ExtensionClasses::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(LinkValidator::class)]
#[UsesClass(Loader::class)]
#[UsesClass(MarkdownDocument::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(Formatter::class)]
#[UsesClass(ItemRecord::class)]
#[UsesClass(Options::class)]
#[UsesClass(ConditionOrder::class)]
#[UsesClass(LiteralMask::class)]
#[UsesClass(SystemResponse::class)]
#[UsesClass(Validator::class)]
#[UsesClass(Wording::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(Item::class)]
#[UsesClass(ItemValidator::class)]
#[UsesClass(Project::class)]
#[UsesClass(Source::class)]
#[UsesClass(TestReference::class)]
#[UsesClass(Analysis::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Claims::class)]
#[UsesClass(Coverage::class)]
#[UsesClass(EvidenceMatcher::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(UnitCollector::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(SourceRegistry::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(Unit::class)]
#[UsesClass(TestRegistry::class)]
#[UsesClass(RunnerConfig::class)]
#[UsesClass(TestResult::class)]
#[UsesClass(VerificationResult::class)]
#[UsesClass(Verifier::class)]
#[UsesClass(TestExecution::class)]
#[UsesClass(TargetResults::class)]
#[Small]
final class ExecutorTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testExecuteLintCountsTheValidatedItems(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['passed' => true, 'message' => '1 items validated.'], (new Executor())->execute($loaded, new Options('lint', [])));
    }

    /**
     * @throws JsonException
     */
    public function testExecuteFormatReportsNoChangesForCanonicalDocuments(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['passed' => true, 'changed' => []], (new Executor())->execute($loaded, new Options('format', ['check' => true])));
    }

    /**
     * @throws JsonException
     */
    public function testExecuteFormatFailsTheCheckWhenADocumentNeedsFormatting(): void
    {
        $project = new ProjectDirectory();
        $project->put('requirements.yaml', "{version: 1, definitions: [definition.yaml]}\n");
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['passed' => false, 'changed' => [$project->path('requirements.yaml')]], (new Executor())->execute($loaded, new Options('format', ['check' => true])));
        self::assertSame("{version: 1, definitions: [definition.yaml]}\n", $project->read('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testExecuteFormatRewritesDocumentsAndPasses(): void
    {
        $project = new ProjectDirectory();
        $project->put('requirements.yaml', "{version: 1, definitions: [definition.yaml]}\n");
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['passed' => true, 'changed' => [$project->path('requirements.yaml')]], (new Executor())->execute($loaded, new Options('format', [])));
        self::assertSame("version: 1\ndefinitions:\n  - definition.yaml\n", $project->read('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCheckReportsValidEvidence(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $report = (new Executor())->execute($loaded, new Options('check', []));
        self::assertSame(['passed', 'mode', 'errors', 'evidence'], array_keys($report));
        self::assertTrue($report['passed']);
        self::assertSame('configured', $report['mode']);
        self::assertSame([], $report['errors']);
        self::assertSame(['SPEC-001'], array_keys(Fields::mapping($report['evidence'], 'evidence')));
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCheckReadsLiveSources(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $report = (new Executor())->execute($loaded, new Options('check', ['live' => true]));
        self::assertTrue($report['passed']);
        self::assertSame('live', $report['mode']);
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCheckFailsOnInvalidEvidence(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [[...ProjectDirectory::item(), 'evidence' => [['selector' => '#a', 'quote' => 'Names shall end with a letter.']]]]]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $report = (new Executor())->execute($loaded, new Options('check', []));
        self::assertFalse($report['passed']);
        self::assertNotSame([], $report['errors']);
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCoverageReportsTheConfiguredMode(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $report = (new Executor())->execute($loaded, new Options('coverage', []));
        self::assertTrue($report['passed']);
        self::assertSame('configured', $report['mode']);
        self::assertSame('requirements-coverage', $report['type']);
        self::assertNull($report['diff']);
        self::assertSame(3, Fields::mapping($report['overall'], 'overall')['total']);
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCoverageReportsTheLiveMode(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame('live', (new Executor())->execute($loaded, new Options('coverage', ['live' => true]))['mode']);
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCoverageAppliesTheThresholdOptions(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $report = (new Executor())->execute($loaded, new Options('coverage', ['min-coverage' => '100', 'min-diff-coverage' => '50']));
        self::assertFalse($report['passed']);
        self::assertSame(['Overall source coverage is below 100%.', 'A positive differential threshold requires --snapshot.'], $report['errors']);
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCoverageWritesAndComparesASnapshot(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $written = (new Executor())->execute($loaded, new Options('coverage', ['write-snapshot' => $project->path('snapshot.json')]));
        self::assertTrue($written['passed']);
        $snapshot = Fields::mapping(json_decode($project->read('snapshot.json'), true, 512, JSON_THROW_ON_ERROR), 'snapshot');
        self::assertSame(['version', 'type', 'units'], array_keys($snapshot));
        self::assertSame('requirements-snapshot', $snapshot['type']);
        self::assertStringEndsWith("}\n", $project->read('snapshot.json'));
        self::assertStringContainsString("\n    \"type\": \"requirements-snapshot\",\n", $project->read('snapshot.json'));
        $compared = (new Executor())->execute($loaded, new Options('coverage', ['snapshot' => $project->path('snapshot.json'), 'min-diff-coverage' => '100']));
        self::assertTrue($compared['passed']);
        self::assertSame(['total' => 0, 'accounted' => 0, 'supported' => 0, 'unsupported' => 0, 'uncovered' => 0, 'percentage' => null], $compared['diff']);
        self::assertSame([], $compared['removed']);
    }

    /**
     * @throws JsonException
     */
    public function testExecuteCoverageRefusesASnapshotOfInvalidEvidence(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [[...ProjectDirectory::item(), 'evidence' => [['selector' => '#a', 'quote' => 'Names shall end with a letter.']]]]]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Cannot write a coverage snapshot with invalid source evidence.');
        (new Executor())->execute($loaded, new Options('coverage', ['write-snapshot' => $project->path('snapshot.json')]));
    }

    /**
     * @throws JsonException
     */
    public function testExecuteSpecListsRecordsWithoutRunningTests(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['passed' => true, 'no_test' => true, 'strict' => false, 'all' => false, 'specifications' => ['SPEC-001' => [
            'id' => 'SPEC-001',
            'kind' => 'specification',
            'statement' => 'When a name is read, the parser shall require a leading letter.',
            'support' => 'supported',
            'source' => 'manual',
            'origin' => 'sourced',
            'reason' => '',
            'labels' => [],
            'category' => '',
            'requirements' => [],
            'related' => [],
            'design' => [],
            'metadata' => [],
            'test_references' => [],
            'status' => 'not-run',
            'tests' => 0,
            'passed_targets' => null,
            'total_targets' => 0,
            'message' => '',
            'deferred_targets' => 0,
        ]], 'errors' => []], (new Executor())->execute($loaded, new Options('spec', ['no-test' => true])));
    }

    /**
     * @throws JsonException
     */
    public function testExecuteSpecAcceptsEmptySelectionByDefault(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['passed' => true, 'no_test' => true, 'strict' => false, 'all' => false, 'specifications' => [], 'errors' => []], (new Executor())->execute($loaded, new Options('spec', ['no-test' => true, 'id' => 'MISSING'])));
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, string> $statuses
     * @throws JsonException
     */
    #[DataProvider('providerExecuteSpec')]
    public function testExecuteSpecPassesOnlyWhenEveryItemPasses(array $items, array $statuses, bool $passed): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', [
            'version' => 1,
            'definitions' => ['definition.yaml'],
            'extensions' => ['runners' => ['custom' => CountingRunner::class]],
            'runners' => ['example' => ['extension' => 'custom', 'command' => ['example']]],
        ]);
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => $items]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $report = (new Executor())->execute($loaded, new Options('spec', []));
        self::assertSame($passed, $report['passed']);
        self::assertFalse($report['no_test']);
        self::assertSame([], $report['errors']);
        self::assertSame($statuses, array_column(Fields::mapping($report['specifications'], 'specifications'), 'status', 'id'));
    }

    /**
     * @return array<string, array{list<array<string, mixed>>, array<string, string>, bool}>
     */
    public static function providerExecuteSpec(): array
    {
        $passing = [...ProjectDirectory::item(), 'id' => 'PASSING', 'tests' => [['runner' => 'example', 'target' => 'shared']]];
        $failing = [...ProjectDirectory::item(), 'id' => 'FAILING', 'tests' => [['runner' => 'example', 'target' => 'empty']]];
        $unverified = [...ProjectDirectory::item(), 'id' => 'UNVERIFIED'];
        $unsupported = [...ProjectDirectory::item(), 'id' => 'UNSUPPORTED', 'status' => 'unsupported', 'reason' => 'The external system owns this behavior.'];
        $requirement = ['id' => 'REQ-001', 'kind' => 'requirement', 'statement' => 'Names may contain digits.', 'evidence' => [['selector' => '#b', 'quote' => 'Names may contain digits.']]];
        return [
            'passing' => [[$passing], ['PASSING' => 'passed'], true],
            'passing, unsupported and not applicable' => [[$passing, $unsupported, $requirement], ['PASSING' => 'passed', 'UNSUPPORTED' => 'unsupported', 'REQ-001' => 'not-applicable'], true],
            'failing first' => [[$failing, $passing], ['FAILING' => 'failed', 'PASSING' => 'passed'], false],
            'failing last' => [[$passing, $failing], ['PASSING' => 'passed', 'FAILING' => 'failed'], false],
            'unverified' => [[$unverified, $passing], ['UNVERIFIED' => 'unverified', 'PASSING' => 'passed'], true],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testExecuteRejectsAnUnknownCommand(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unknown command: unknown');
        (new Executor())->execute($loaded, new Options('unknown', []));
    }
    /**
     * @throws JsonException
     */
    public function testStrictSpecRequiresLinksEvenWithoutTestExecution(): void
    {
        $project = new ProjectDirectory();
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $report = (new Executor())->execute($loaded, new Options('spec', ['no-test' => true, 'strict' => true]));
        self::assertFalse($report['passed']);
        self::assertSame(['SPEC-001: No tests linked.'], $report['errors']);
        $empty = (new Executor())->execute($loaded, new Options('spec', ['id' => 'MISSING', 'strict' => true]));
        self::assertFalse($empty['passed']);
        self::assertSame(['No specifications or requirements selected.'], $empty['errors']);
    }
}
