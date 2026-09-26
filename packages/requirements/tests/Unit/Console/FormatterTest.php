<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Requirements\Config\Markdown\Render\CardWriter;
use Requirements\Config\Markdown\Render\FieldWriter;
use Requirements\Config\Markdown\Render\Record;
use Requirements\Config\Markdown\StaticBadge;
use Requirements\Config\MarkdownDocument;
use Requirements\Config\SchemaValidator;
use Requirements\Console\Formatter;
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
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\Registry as SourceRegistry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\TextSource;
use Requirements\Test\Registry as TestRegistry;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Formatter::class)]
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
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(SourceRegistry::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(TestRegistry::class)]
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
#[UsesClass(CardWriter::class)]
#[UsesClass(FieldWriter::class)]
#[UsesClass(Record::class)]
#[UsesClass(StaticBadge::class)]
#[Small]
final class FormatterTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testFormatLeavesCanonicalDocumentsUnchanged(): void
    {
        $project = new ProjectDirectory();
        $configuration = $project->read('requirements.yaml');
        $definition = $project->read('definition.yaml');
        self::assertSame([], (new Formatter())->format([$project->path('requirements.yaml'), $project->path('definition.yaml')], false));
        self::assertSame($configuration, $project->read('requirements.yaml'));
        self::assertSame($definition, $project->read('definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testFormatRewritesDocumentsInTheirCanonicalForm(): void
    {
        $project = new ProjectDirectory();
        $definition = $project->read('definition.yaml');
        $project->put('requirements.yaml', "{version: 1, definitions: [definition.yaml], coverage: {minimum: 0}}\n");
        $project->put('definition.yaml', "version: 1\nsource: {id: manual, uri: source.html, format: html, selector: main p}\nitems:\n  - {id: SPEC-001, statement: 'When a name is read, the parser shall require a leading letter.', evidence: [{selector: '#a', quote: 'Names shall start with a letter.'}]}\n");
        $files = [$project->path('requirements.yaml'), $project->path('definition.yaml')];
        self::assertSame($files, (new Formatter())->format($files, false));
        self::assertSame("version: 1\ndefinitions:\n  - definition.yaml\ncoverage:\n  minimum: 0\n", $project->read('requirements.yaml'));
        self::assertSame($definition, $project->read('definition.yaml'));
        self::assertSame([], (new Formatter())->format($files, false));
    }

    /**
     * @throws JsonException
     */
    public function testFormatOnlyReportsChangesWhenChecking(): void
    {
        $project = new ProjectDirectory();
        $unformatted = "version: 1\nsource: {id: manual, uri: source.html, format: html, selector: main p}\nitems:\n  - {id: SPEC-001, statement: 'When a name is read, the parser shall require a leading letter.', evidence: [{selector: '#a', quote: 'Names shall start with a letter.'}]}\n";
        $project->put('definition.yaml', $unformatted);
        self::assertSame([$project->path('definition.yaml')], (new Formatter())->format([$project->path('requirements.yaml'), $project->path('definition.yaml')], true));
        self::assertSame($unformatted, $project->read('definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testFormatRendersMarkdownDefinitionsAsMarkdown(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true]]);
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n\n**origin**\n\noriginal\n\n**reason**\n\nDemonstrate the runner contract.\n\n**labels**\n\n- grammar\n");
        $files = [$project->path('requirements.yaml'), $project->path('definition.md')];
        self::assertSame([$project->path('definition.md')], (new Formatter())->format($files, false, ['experimental' => true]));
        self::assertSame("---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\n![original](https://img.shields.io/badge/origin-original-blue)\n![grammar](https://img.shields.io/badge/label-grammar-blue)\n\nThe converter shall uppercase letters.\n\n**rationale**\n\nDemonstrate the runner contract.\n", $project->read('definition.md'));
        self::assertSame([], (new Formatter())->format($files, true, ['experimental' => true]));
    }

    /**
     * @throws JsonException
     */
    public function testFormatRequiresTheMarkdownOptIn(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Markdown definitions require markdown.experimental: true.');
        (new Formatter())->format([$project->path('requirements.yaml'), $project->path('definition.md')], true);
    }

    /**
     * @throws JsonException
     */
    public function testFormatReadsTheFirstFileAsTheConfiguration(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        (new Formatter())->format([$project->path('definition.yaml')], true);
    }

    /**
     * @throws JsonException
     */
    public function testFormatRejectsAnInvalidDefinition(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.yaml', "version: 1\nsource: null\nitems: []\nlables: []\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('lables');
        (new Formatter())->format([$project->path('requirements.yaml'), $project->path('definition.yaml')], true);
    }

    /**
     * @throws JsonException
     */
    public function testFormatKeepsBundledSchemaDeclarationsAndEmptyMaps(): void
    {
        $project = new ProjectDirectory();
        $loader = new Loader();
        $data = $loader->document($project->path('definition.yaml'));
        $data = ['$schema' => SchemaValidator::BASE . 'definition.schema.json', ...$data];
        $project->write('definition.yaml', $data);
        $project->put('definition.yaml', $project->read('definition.yaml') . "    metadata: {}\n");
        $project->write('requirements.yaml', ['$schema' => SchemaValidator::BASE . 'config.schema.json', 'version' => 1, 'definitions' => ['definition.yaml']]);
        $loaded = $loader->load($project->path('requirements.yaml'));
        (new Formatter())->format($loaded->files, false);
        self::assertCount(1, $loader->load($project->path('requirements.yaml'))->items);
        $formatted = file_get_contents($project->path('definition.yaml'));
        self::assertIsString($formatted);
        self::assertStringContainsString('metadata: {  }', $formatted);
    }
}
