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
use Tests\Fake\UnreadableSource;

#[CoversClass(Analyzer::class)]
#[UsesClass(Analysis::class)]
#[UsesClass(Claims::class)]
#[UsesClass(Coverage::class)]
#[UsesClass(EvidenceMatcher::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(UnitCollector::class)]
#[UsesClass(Loader::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(Item::class)]
#[UsesClass(Project::class)]
#[UsesClass(Source::class)]
#[UsesClass(Registry::class)]
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
#[UsesClass(ItemValidator::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(RunnerRegistry::class)]
#[Small]
final class AnalyzerTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testAnalyzeUsesIndependentScopeAndReasonedDisposition(): void
    {
        $workspace = new ProjectDirectory();
        $loader = new Loader();
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $unsupported = ProjectDirectory::item();
        $unsupported['id'] = 'UNSUPPORTED-001';
        $unsupported['status'] = 'unsupported';
        $unsupported['reason'] = 'This library reads grammars; it does not generate C.';
        $unsupported['statement'] = 'The generator shall produce C code.';
        $unsupported['evidence'] = [['selector' => '#c', 'quote' => 'The generator shall produce C code.']];
        $definition['items'] = [ProjectDirectory::item(), $unsupported];
        $workspace->write('definition.yaml', $definition);
        $project = $loader->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        self::assertSame([], $analysis->errors);
        self::assertSame(['total' => 3, 'accounted' => 2, 'supported' => 1, 'unsupported' => 1, 'uncovered' => 1, 'percentage' => 200.0 / 3], $analysis->summary());
        self::assertFalse((new Coverage())->report($project, $analysis, minimum: 80)['passed']);
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvalidEvidence')]
    public function testAnalyzeFailsClosedOnInvalidEvidence(string $selector, string $quote, string $error): void
    {
        $workspace = new ProjectDirectory();
        $loader = new Loader();
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $item = ProjectDirectory::item();
        $item['evidence'] = [['selector' => $selector, 'quote' => $quote]];
        $definition['items'] = [$item];
        $workspace->write('definition.yaml', $definition);
        $analysis = (new Analyzer())->analyze($loader->load($workspace->directory . '/requirements.yaml'));
        self::assertStringContainsString($error, implode(' ', $analysis->errors));
        self::assertSame(0, $analysis->summary()['accounted']);
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function providerInvalidEvidence(): array
    {
        return [
            ['#missing', 'Nothing.', 'exactly one'],
            ['main p', 'Names shall start with a letter.', 'exactly one'],
            ['#a', 'start with a letter', 'differs'],
            ['#outside', 'Outside scope.', 'outside'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeReportsAnEmptyScopeAsAnErrorAndNotAsFullCoverage(): void
    {
        $workspace = new ProjectDirectory();
        $definition = (new Loader())->document($workspace->directory . '/definition.yaml');
        $definition['source'] = ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#missing'];
        $definition['items'] = [];
        $workspace->write('definition.yaml', $definition);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertNotEmpty($analysis->errors);
        self::assertNull($analysis->summary()['percentage']);
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeNeedsASpecificationToClaimARequirementSharedAcrossDefinitions(): void
    {
        $workspace = new ProjectDirectory();
        $loader = new Loader();
        $definition = $loader->document($workspace->directory . '/definition.yaml');
        $requirement = ProjectDirectory::item();
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

    /**
     * @throws JsonException
     */
    public function testAnalyzeCountsUnitsSharedByScopesOnce(): void
    {
        $workspace = new ProjectDirectory();
        $definition = (new Loader())->document($workspace->directory . '/definition.yaml');
        $definition['source'] = ['id' => 'overlap', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#a'];
        $definition['items'] = [];
        $workspace->write('other.yaml', $definition);
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml', 'other.yaml']]);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertSame(3, $analysis->summary()['total']);
        self::assertSame(100.0, $analysis->summary($analysis->scopes['overlap'])['percentage']);
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeRejectsEvidenceQuotingRepeatedUnitsAndCountsThemUncovered(): void
    {
        $workspace = new ProjectDirectory();
        file_put_contents($workspace->directory . '/source.html', '<main><p>Repeated text.</p><p>Repeated text.</p></main>');
        $workspace->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [['id' => 'SPEC-001', 'statement' => 'The reader shall preserve text.', 'evidence' => [['selector' => '#:~:text=Repeated%20text.', 'quote' => 'Repeated text.']]]]]);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertStringContainsString('exactly one unit', implode(' ', $analysis->errors));
        self::assertSame(2, $analysis->summary()['total']);
        self::assertSame(0, $analysis->summary()['accounted']);
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeRecordsScopesEvidenceAndClaims(): void
    {
        $workspace = new ProjectDirectory();
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $source = $project->sources['manual'];
        $analysis = (new Analyzer())->analyze($project);
        $keys = [(new Unit('/html/body/main/p[1]', ''))->key($source), (new Unit('/html/body/main/p[2]', ''))->key($source), (new Unit('/html/body/main/p[3]', ''))->key($source)];
        self::assertSame([], $analysis->errors);
        self::assertSame(['manual' => $keys], $analysis->scopes);
        self::assertSame(['SPEC-001' => [$keys[0]]], $analysis->evidence);
        self::assertSame(['SPEC-001'], array_keys($analysis->units[$keys[0]]->claims));
        self::assertSame(['total' => 3, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 2, 'percentage' => 100.0 / 3], $analysis->summary());
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeReportsAFailingSourceExtensionForTheScopeAndEveryEvidenceEntry(): void
    {
        $workspace = new ProjectDirectory();
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'extensions' => ['sources' => ['unreadable' => UnreadableSource::class]]]);
        $workspace->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'unreadable', 'selector' => 'main p'], 'items' => [ProjectDirectory::item()]]);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertSame(['manual: Cannot read source.html.', 'SPEC-001: Cannot read source.html.'], $analysis->errors);
        self::assertSame([], $analysis->units);
        self::assertSame(['manual' => []], $analysis->scopes);
        self::assertSame(['SPEC-001' => []], $analysis->evidence);
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeContinuesAfterAFailingScope(): void
    {
        $workspace = new ProjectDirectory();
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['broken.yaml', 'definition.yaml'], 'extensions' => ['sources' => ['unreadable' => UnreadableSource::class]]]);
        $workspace->write('broken.yaml', ['version' => 1, 'source' => ['id' => 'broken', 'uri' => 'broken.html', 'format' => 'unreadable', 'selector' => 'p'], 'items' => []]);
        $analysis = (new Analyzer())->analyze((new Loader())->load($workspace->directory . '/requirements.yaml'));
        self::assertSame(['broken: Cannot read broken.html.'], $analysis->errors);
        self::assertSame([], $analysis->scopes['broken']);
        self::assertCount(3, $analysis->scopes['manual']);
        self::assertSame(['total' => 3, 'accounted' => 1, 'supported' => 1, 'unsupported' => 0, 'uncovered' => 2, 'percentage' => 100.0 / 3], $analysis->summary());
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeResolvesTheEvidenceOfItemsAfterAnItemWithoutSource(): void
    {
        $workspace = new ProjectDirectory();
        $workspace->write('requirements.yaml', ['version' => 1, 'definitions' => ['charter.yaml', 'definition.yaml']]);
        $workspace->write('charter.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-000', 'statement' => 'The parser shall report its version.', 'origin' => 'original', 'reason' => 'A project convention.']]]);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        self::assertSame(['SPEC-000', 'SPEC-001'], array_keys($project->items));
        self::assertSame(['SPEC-000' => [], 'SPEC-001' => [(new Unit('/html/body/main/p[1]', ''))->key($project->sources['manual'])]], $analysis->evidence);
        self::assertSame(1, $analysis->summary()['accounted']);
    }

    /**
     * @throws JsonException
     */
    public function testAnalyzeKeepsTheValidEvidenceOfAnItemThatClaimsNothing(): void
    {
        $workspace = new ProjectDirectory();
        $item = ProjectDirectory::item();
        $item['evidence'] = [['selector' => '#missing', 'quote' => 'Nothing.'], ['selector' => '#b', 'quote' => 'Names may contain digits.'], ['selector' => '#outside', 'quote' => 'Outside scope.']];
        $workspace->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [$item]]);
        $project = (new Loader())->load($workspace->directory . '/requirements.yaml');
        $analysis = (new Analyzer())->analyze($project);
        self::assertSame(['SPEC-001: Evidence must select exactly one unit: #missing', 'SPEC-001: Evidence is outside the declared scope: #outside'], $analysis->errors);
        self::assertSame(['SPEC-001' => [(new Unit('/html/body/main/p[2]', ''))->key($project->sources['manual'])]], $analysis->evidence);
        self::assertSame(0, $analysis->summary()['accounted']);
    }

    #[DataProvider('providerReadingModes')]
    public function testAnalyzeReadsPinnedSnapshotsUnlessLive(bool $live, string $text): void
    {
        $workspace = new ProjectDirectory();
        $workspace->put('current.txt', 'New text.');
        $workspace->put('snapshot.txt', 'Old text.');
        $source = new Source('notes', 'current.txt', 'text', 'lines:1', 'snapshot.txt', hash('sha256', 'Old text.'));
        $item = new Item('SPEC-001', 'specification', 'The reader shall preserve text.', 'supported', $source, [new Excerpt('lines:1', $text)], [], [], [], [], '', 'sourced', '', 'definition.yaml', []);
        $project = new Project($workspace->directory, ['SPEC-001' => $item], ['notes' => $source], [], [], [], 0.0, 0.0, [], []);
        $analysis = (new Analyzer())->analyze($project, $live);
        $key = (new Unit('line:1', $text))->key($source);
        self::assertSame([], $analysis->errors);
        self::assertSame($text, $analysis->units[$key]->unit->text);
        self::assertSame(['SPEC-001' => [$key]], $analysis->evidence);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function providerReadingModes(): array
    {
        return [
            'snapshot' => [false, 'Old text.'],
            'live' => [true, 'New text.'],
        ];
    }
}
