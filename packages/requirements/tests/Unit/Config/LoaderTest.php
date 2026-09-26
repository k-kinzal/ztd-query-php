<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Bootstrap;
use Requirements\Config\CoverageThresholds;
use Requirements\Config\DefinitionReader;
use Requirements\Config\Definitions;
use Requirements\Config\DocumentReader;
use Requirements\Config\ExtensionClasses;
use Requirements\Config\JsonSchemaFile;
use Requirements\Config\LinkValidator;
use Requirements\Config\Loader;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\CardReader;
use Requirements\Config\Markdown\FieldReader;
use Requirements\Config\Markdown\FieldSections;
use Requirements\Config\Markdown\Frontmatter;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Profile\AllowedBlocks;
use Requirements\Config\Markdown\Profile\BlockKind;
use Requirements\Config\Markdown\Profile\DocumentSchema;
use Requirements\Config\Markdown\Profile\Occurrences;
use Requirements\Config\Markdown\Profile\SectionBlocks;
use Requirements\Config\Markdown\Profile\TextConstraint;
use Requirements\Config\Markdown\QuotationBlocks;
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
use Requirements\Model\TestReference;
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\Registry as SourceRegistry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\TextSource;
use Requirements\Test\Registry as RunnerRegistry;
use Requirements\Test\RunnerConfig;
use Symfony\Component\Yaml\Exception\ParseException;
use Tests\Fake\CountingRunner;
use Tests\Fake\MemorySource;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Loader::class)]
#[UsesClass(CoverageThresholds::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(Definitions::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(ExtensionClasses::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(LinkValidator::class)]
#[UsesClass(MarkdownDocument::class)]
#[UsesClass(Badges::class)]
#[UsesClass(CardReader::class)]
#[UsesClass(FieldReader::class)]
#[UsesClass(FieldSections::class)]
#[UsesClass(Frontmatter::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(AllowedBlocks::class)]
#[UsesClass(BlockKind::class)]
#[UsesClass(DocumentSchema::class)]
#[UsesClass(Occurrences::class)]
#[UsesClass(SectionBlocks::class)]
#[UsesClass(TextConstraint::class)]
#[UsesClass(QuotationBlocks::class)]
#[UsesClass(SchemaValidator::class)]
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
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(SourceRegistry::class)]
#[UsesClass(RunnerRegistry::class)]
#[UsesClass(Bootstrap::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(RunnerConfig::class)]
#[Small]
final class LoaderTest extends TestCase
{
    /**
     * @param array<string, mixed> $changes
     * @throws JsonException
     */
    #[DataProvider('providerInvalidItems')]
    public function testLoadRejectsInvalidDefinitions(array $changes, string $message): void
    {
        $project = new ProjectDirectory();
        $definition = (new Loader())->document($project->directory . '/definition.yaml');
        $definition['items'] = [array_replace(ProjectDirectory::item(), $changes)];
        $project->write('definition.yaml', $definition);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Loader())->load($project->directory . '/requirements.yaml');
    }

    /**
     * @return list<array{array<string, mixed>, string}>
     */
    public static function providerInvalidItems(): array
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

    /**
     * @throws JsonException
     */
    public function testLoadReturnsProject(): void
    {
        $project = new ProjectDirectory();
        $directory = realpath($project->directory);
        self::assertIsString($directory);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame($directory, $loaded->directory);
        self::assertSame(['SPEC-001'], array_keys($loaded->items));
        self::assertEquals(['manual' => new Source('manual', 'source.html', 'html', 'main p')], $loaded->sources);
        self::assertSame([], $loaded->runners);
        self::assertSame([], $loaded->sourceExtensions);
        self::assertSame([], $loaded->runnerExtensions);
        self::assertSame(0.0, $loaded->minimum);
        self::assertSame(0.0, $loaded->diffMinimum);
        self::assertSame([], $loaded->sourceThresholds);
        self::assertSame([$directory . '/requirements.yaml', $directory . '/definition.yaml'], $loaded->files);
        self::assertSame([], $loaded->markdown);
    }

    /**
     * @throws JsonException
     */
    public function testLoadResolvesConfigurationPath(): void
    {
        $project = new ProjectDirectory();
        $project->put('nested/.keep', '');
        $directory = realpath($project->directory);
        self::assertIsString($directory);
        $loaded = (new Loader())->load($project->path('nested/../requirements.yaml'));
        self::assertSame($directory, $loaded->directory);
        self::assertSame($directory . '/requirements.yaml', $loaded->files[0]);
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsMissingConfiguration(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Configuration does not exist: ' . $project->path('missing.yaml'));
        (new Loader())->load($project->path('missing.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadValidatesConfigurationSchema(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'reports' => []]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('requirements.yaml: schema validation failed:');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsMalformedConfiguration(): void
    {
        $project = new ProjectDirectory();
        $project->put('requirements.yaml', "version: [\n");
        $this->expectException(ParseException::class);
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadIncludesBootstrap(): void
    {
        $project = new ProjectDirectory();
        $project->put('tools/bootstrap.php', "<?php\nfile_put_contents(__DIR__ . '/loaded.txt', 'loaded;', FILE_APPEND);\n");
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'bootstrap' => 'tools/bootstrap.php']);
        (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame('loaded;', $project->read('tools/loaded.txt'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsMissingBootstrap(): void
    {
        $project = new ProjectDirectory();
        $directory = realpath($project->directory);
        self::assertIsString($directory);
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'bootstrap' => 'missing.php']);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("Bootstrap does not exist: $directory/missing.php");
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsDefinitionPatternWithoutMatches(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml', 'specs/*.yaml']]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Definition pattern has no matches: specs/*.yaml');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsDefinitionWithoutSource(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.yaml', "version: 1\nitems: []\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('definition.yaml: schema validation failed: {"/":["The required properties (source) are missing"]}');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsDuplicateSourceId(): void
    {
        $project = new ProjectDirectory();
        $project->write('other.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#b'], 'items' => []]);
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml', 'other.yaml']]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Duplicate source ID: manual');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsDuplicateItemId(): void
    {
        $project = new ProjectDirectory();
        $project->write('other.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-001', 'statement' => 'The parser shall read.', 'origin' => 'original', 'reason' => 'Local rule.']]]);
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml', 'other.yaml']]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Duplicate item ID: SPEC-001');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadReadsRunners(): void
    {
        $project = new ProjectDirectory();
        $directory = realpath($project->directory);
        self::assertIsString($directory);
        $item = ProjectDirectory::item();
        $item['tests'] = [['runner' => 'unit', 'target' => 'Sample\\PassingTest::testPass']];
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [$item]]);
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'runners' => ['unit' => ['extension' => 'phpunit', 'command' => ['phpunit'], 'cwd' => 'tests', 'timeout' => 5], 'features' => ['extension' => 'behat', 'command' => ['behat']]]]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertEquals(['unit' => new RunnerConfig('phpunit', ['phpunit'], "$directory/tests", 5.0), 'features' => new RunnerConfig('behat', ['behat'], "$directory/.", 60.0)], $loaded->runners);
        self::assertSame('unit', $loaded->items['SPEC-001']->tests[0]->runner);
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsUnknownRunnerExtension(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'runners' => ['unit' => ['extension' => 'missing', 'command' => ['phpunit']]]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unknown runner extension: missing');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsTestLinkedToUnknownRunner(): void
    {
        $project = new ProjectDirectory();
        $item = ProjectDirectory::item();
        $item['tests'] = [['runner' => 'phpunit', 'target' => 'Sample\\PassingTest::testPass']];
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [$item]]);
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'runners' => ['unit' => ['extension' => 'phpunit', 'command' => ['phpunit']]]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("SPEC-001: unknown runner 'phpunit'.");
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadReadsCoverage(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'coverage' => ['minimum' => 80, 'diff_minimum' => 12.5, 'sources' => ['manual' => 50]]]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(80.0, $loaded->minimum);
        self::assertSame(12.5, $loaded->diffMinimum);
        self::assertSame(['manual' => 50.0], $loaded->sourceThresholds);
    }

    /**
     * @throws JsonException
     */
    public function testLoadDefaultsCoverageToZero(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml']]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(0.0, $loaded->minimum);
        self::assertSame(0.0, $loaded->diffMinimum);
        self::assertSame([], $loaded->sourceThresholds);
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsCoverageOutOfRange(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'coverage' => ['minimum' => 101]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('requirements.yaml: schema validation failed: {"/coverage/minimum":');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsUnknownSourceThreshold(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'coverage' => ['sources' => ['manual' => 50, 'other' => 50]]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unknown source threshold: other');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsUnknownSourceFormat(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'message', 'format' => 'memory', 'selector' => 'all'], 'items' => []]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unknown source extension: memory');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRegistersExtensions(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'message', 'format' => 'memory', 'selector' => 'all'], 'items' => []]);
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'extensions' => ['sources' => ['memory' => MemorySource::class], 'runners' => ['counting' => CountingRunner::class]], 'runners' => ['count' => ['extension' => 'counting', 'command' => ['count']]]]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['memory' => MemorySource::class], $loaded->sourceExtensions);
        self::assertSame(['counting' => CountingRunner::class], $loaded->runnerExtensions);
        self::assertSame('counting', $loaded->runners['count']->extension);
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsSourceExtensionOfWrongType(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'extensions' => ['sources' => ['memory' => CountingRunner::class]]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(CountingRunner::class . ' must implement SourceExtension.');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsRunnerExtensionOfWrongType(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'extensions' => ['runners' => ['counting' => MemorySource::class]]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage(MemorySource::class . ' must implement RunnerExtension.');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsUnknownExtensionKind(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.yaml'], 'extensions' => ['reporters' => ['memory' => MemorySource::class]]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('requirements.yaml: schema validation failed:');
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testLoadPassesMarkdownOptions(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n\n**origin**\n\noriginal\n\n**reason**\n\nDemonstrate the loader.\n");
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true]]);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame(['experimental' => true], $loaded->markdown);
        self::assertSame(['ORIGINAL-001'], array_keys($loaded->items));
    }

    /**
     * @throws JsonException
     */
    public function testLoadRejectsMarkdownWithoutOptions(): void
    {
        $project = new ProjectDirectory();
        $directory = realpath($project->directory);
        self::assertIsString($directory);
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n");
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md']]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$directory/definition.md: Markdown definitions require markdown.experimental: true.");
        (new Loader())->load($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testDocumentReadsDefinitionByDefault(): void
    {
        $project = new ProjectDirectory();
        self::assertSame(['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [ProjectDirectory::item()]], (new Loader())->document($project->path('definition.yaml')));
    }

    /**
     * @throws JsonException
     */
    public function testDocumentReadsConfiguration(): void
    {
        $project = new ProjectDirectory();
        self::assertSame(['version' => 1, 'definitions' => ['definition.yaml'], 'coverage' => ['minimum' => 0]], (new Loader())->document($project->path('requirements.yaml'), 'config'));
    }

    /**
     * @throws JsonException
     */
    public function testDocumentValidatesDefaultKindAsDefinition(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('requirements.yaml') . ': schema validation failed:');
        (new Loader())->document($project->path('requirements.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testDocumentReadsMarkdownWithOptions(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n\n**origin**\n\noriginal\n\n**reason**\n\nDemonstrate the loader.\n");
        self::assertSame(['version' => 1, 'source' => null, 'items' => [['id' => 'ORIGINAL-001', 'statement' => 'The converter shall uppercase letters.', 'origin' => 'original', 'reason' => 'Demonstrate the loader.']]], (new Loader())->document($project->path('definition.md'), 'definition', ['experimental' => true]));
    }

    /**
     * @throws JsonException
     */
    public function testDocumentRejectsMarkdownWithoutOptions(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('definition.md') . ': Markdown definitions require markdown.experimental: true.');
        (new Loader())->document($project->path('definition.md'));
    }
}
