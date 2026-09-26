<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DefinitionReader;
use Requirements\Config\Definitions;
use Requirements\Config\DocumentReader;
use Requirements\Config\JsonSchemaFile;
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
use Requirements\Config\Markdown\Reference;
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
use Requirements\Model\Source;
use Symfony\Component\Yaml\Exception\ParseException;
use Tests\Fake\ProjectDirectory;

#[CoversClass(DefinitionReader::class)]
#[UsesClass(Definitions::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(JsonSchemaFile::class)]
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
#[UsesClass(Reference::class)]
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
#[UsesClass(Source::class)]
#[Small]
final class DefinitionReaderTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testReadReturnsItemsSourcesAndFiles(): void
    {
        $project = new ProjectDirectory();
        $definitions = (new DefinitionReader())->read($project->directory, ['definition.yaml'], []);
        self::assertSame(['SPEC-001'], array_keys($definitions->items));
        self::assertSame('When a name is read, the parser shall require a leading letter.', $definitions->items['SPEC-001']->statement);
        self::assertSame($project->path('definition.yaml'), $definitions->items['SPEC-001']->file);
        self::assertEquals(new Source('manual', 'source.html', 'html', 'main p'), $definitions->sources['manual']);
        self::assertSame($definitions->sources['manual'], $definitions->items['SPEC-001']->source);
        self::assertSame([$project->path('definition.yaml')], $definitions->files);
    }

    /**
     * @throws JsonException
     */
    public function testReadOrdersFilesByPath(): void
    {
        $project = new ProjectDirectory();
        $project->write('b.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-B', 'statement' => 'The parser shall read b.', 'origin' => 'original', 'reason' => 'Local rule.']]]);
        $project->write('a.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-A', 'statement' => 'The parser shall read a.', 'origin' => 'original', 'reason' => 'Local rule.']]]);
        $definitions = (new DefinitionReader())->read($project->directory, ['b.yaml', 'definition.yaml', 'a.yaml'], []);
        self::assertSame([$project->path('a.yaml'), $project->path('b.yaml'), $project->path('definition.yaml')], $definitions->files);
        self::assertSame(['SPEC-A', 'SPEC-B', 'SPEC-001'], array_keys($definitions->items));
    }

    /**
     * @throws JsonException
     */
    public function testReadListsEachFileOnce(): void
    {
        $project = new ProjectDirectory();
        $project->write('defs/b.yaml', ['version' => 1, 'source' => null, 'items' => []]);
        $project->write('defs/a.yaml', ['version' => 1, 'source' => null, 'items' => []]);
        $definitions = (new DefinitionReader())->read($project->directory, ['defs/*.yaml', 'defs/a.yaml', 'defs/*.yaml'], []);
        self::assertSame([$project->path('defs/a.yaml'), $project->path('defs/b.yaml')], $definitions->files);
        self::assertSame([], $definitions->items);
        self::assertSame([], $definitions->sources);
    }

    /**
     * @throws JsonException
     */
    public function testReadResolvesPatternsAgainstDirectory(): void
    {
        $project = new ProjectDirectory();
        $project->write('nested/definition.yaml', ['version' => 1, 'source' => null, 'items' => []]);
        $definitions = (new DefinitionReader())->read($project->path('nested'), ['definition.yaml'], []);
        self::assertSame([$project->path('nested/definition.yaml')], $definitions->files);
    }

    /**
     * @throws JsonException
     */
    public function testReadKeepsItemsWithoutSource(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-001', 'statement' => 'The parser shall read.', 'origin' => 'original', 'reason' => 'Local rule.']]]);
        $definitions = (new DefinitionReader())->read($project->directory, ['definition.yaml'], []);
        self::assertSame([], $definitions->sources);
        self::assertNull($definitions->items['SPEC-001']->source);
        self::assertSame('original', $definitions->items['SPEC-001']->origin);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsPatternWithoutMatches(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Definition pattern has no matches: missing/*.yaml');
        (new DefinitionReader())->read($project->directory, ['definition.yaml', 'missing/*.yaml'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadRequiresOneDefinition(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('At least one definition is required.');
        (new DefinitionReader())->read($project->directory, [], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsDuplicateSourceId(): void
    {
        $project = new ProjectDirectory();
        $project->write('other.yaml', ['version' => 1, 'source' => ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#b'], 'items' => []]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Duplicate source ID: manual');
        (new DefinitionReader())->read($project->directory, ['definition.yaml', 'other.yaml'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsDuplicateItemIdAcrossFiles(): void
    {
        $project = new ProjectDirectory();
        $project->write('other.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-001', 'statement' => 'The parser shall read.', 'origin' => 'original', 'reason' => 'Local rule.']]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Duplicate item ID: SPEC-001');
        (new DefinitionReader())->read($project->directory, ['definition.yaml', 'other.yaml'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsDuplicateItemIdInOneFile(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-001', 'statement' => 'The parser shall read.', 'origin' => 'original', 'reason' => 'Local rule.'], ['id' => 'SPEC-001', 'statement' => 'The parser shall write.', 'origin' => 'original', 'reason' => 'Local rule.']]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Duplicate item ID: SPEC-001');
        (new DefinitionReader())->read($project->directory, ['definition.yaml'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsDefinitionWithoutSourceDeclaration(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.yaml', "version: 1\nitems: []\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('definition.yaml') . ': schema validation failed: {"/":["The required properties (source) are missing"]}');
        (new DefinitionReader())->read($project->directory, ['definition.yaml'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsInvalidItem(): void
    {
        $project = new ProjectDirectory();
        $project->write('definition.yaml', ['version' => 1, 'source' => null, 'items' => [['id' => 'SPEC-001', 'statement' => 'Maybe names are letters', 'origin' => 'original', 'reason' => 'Local rule.']]]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('EARS');
        (new DefinitionReader())->read($project->directory, ['definition.yaml'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsMalformedYaml(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.yaml', "version: [\n");
        $this->expectException(ParseException::class);
        (new DefinitionReader())->read($project->directory, ['definition.yaml'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadPassesMarkdownOptions(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n\n**origin**\n\noriginal\n\n**reason**\n\nDemonstrate the reader.\n");
        $definitions = (new DefinitionReader())->read($project->directory, ['definition.md'], ['experimental' => true]);
        self::assertSame(['ORIGINAL-001'], array_keys($definitions->items));
        self::assertSame('Demonstrate the reader.', $definitions->items['ORIGINAL-001']->reason);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsMarkdownWithoutOptions(): void
    {
        $project = new ProjectDirectory();
        $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# ORIGINAL-001\n\nThe converter shall uppercase letters.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('definition.md') . ': Markdown definitions require markdown.experimental: true.');
        (new DefinitionReader())->read($project->directory, ['definition.md'], []);
    }

    /**
     * @throws JsonException
     */
    public function testReadResolvesMarkdownLinksAcrossFiles(): void
    {
        $project = new ProjectDirectory();
        $project->write('reference.yaml', ['version' => 1, 'source' => ['id' => 'reference', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#b'], 'items' => [['id' => 'REQ-001', 'kind' => 'requirement', 'statement' => 'Names may contain digits.', 'evidence' => [['selector' => '#b', 'quote' => 'Names may contain digits.']]]]]);
        $project->put('a-spec.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-002\n\nThe parser shall accept digits.\n\n**requirements**\n\n- [REQ-001](reference.yaml#req-001)\n");
        $definitions = (new DefinitionReader())->read($project->directory, ['a-spec.md', 'reference.yaml'], ['experimental' => true]);
        self::assertSame(['REQ-001'], $definitions->items['SPEC-002']->requirements);
        self::assertSame([$project->path('a-spec.md'), $project->path('reference.yaml')], $definitions->files);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsMarkdownLinkToWrongFile(): void
    {
        $project = new ProjectDirectory();
        $project->write('reference.yaml', ['version' => 1, 'source' => ['id' => 'reference', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#b'], 'items' => [['id' => 'REQ-001', 'kind' => 'requirement', 'statement' => 'Names may contain digits.', 'evidence' => [['selector' => '#b', 'quote' => 'Names may contain digits.']]]]]);
        $project->put('spec.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-002\n\nThe parser shall accept digits.\n\n**requirements**\n\n- [REQ-001](definition.yaml#req-001)\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('spec.md') . ": link to 'REQ-001' does not point to its loaded definition file.");
        (new DefinitionReader())->read($project->directory, ['spec.md', 'reference.yaml', 'definition.yaml'], ['experimental' => true]);
    }

    /**
     * @throws JsonException
     */
    public function testReadKeepsMarkdownLinksOfEveryFile(): void
    {
        $project = new ProjectDirectory();
        $project->write('reference.yaml', ['version' => 1, 'source' => ['id' => 'reference', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#b'], 'items' => [['id' => 'REQ-001', 'kind' => 'requirement', 'statement' => 'Names may contain digits.', 'evidence' => [['selector' => '#b', 'quote' => 'Names may contain digits.']]]]]);
        $project->put('a.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-002\n\nThe parser shall accept digits.\n\n**requirements**\n\n- [REQ-001](missing.yaml#req-001)\n");
        $project->put('b.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-003\n\nThe converter shall uppercase letters.\n\n**origin**\n\noriginal\n\n**reason**\n\nDemonstrate the reader.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('a.md') . ": link to 'REQ-001' does not point to its loaded definition file.");
        (new DefinitionReader())->read($project->directory, ['a.md', 'b.md', 'reference.yaml'], ['experimental' => true]);
    }
}
