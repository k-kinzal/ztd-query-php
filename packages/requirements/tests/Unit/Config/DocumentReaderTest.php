<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DocumentReader;
use Requirements\Config\JsonSchemaFile;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\CardReader;
use Requirements\Config\Markdown\Citation;
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
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Tests\Fake\ProjectDirectory;

#[CoversClass(DocumentReader::class)]
#[UsesClass(Fields::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(MarkdownDocument::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(Badges::class)]
#[UsesClass(CardReader::class)]
#[UsesClass(Citation::class)]
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
#[UsesClass(Source::class)]
#[Small]
final class DocumentReaderTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testReadParsesYamlDefinitionAsObjects(): void
    {
        $project = new ProjectDirectory();
        $expected = (object) [
            'version' => 1,
            'source' => (object) ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'],
            'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'When a name is read, the parser shall require a leading letter.', 'evidence' => [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']]]],
        ];
        self::assertEquals($expected, (new DocumentReader())->read($project->path('definition.yaml'), 'definition'));
    }

    /**
     * @throws JsonException
     */
    public function testReadParsesYamlConfiguration(): void
    {
        $project = new ProjectDirectory();
        $expected = (object) ['version' => 1, 'definitions' => ['definition.yaml'], 'coverage' => (object) ['minimum' => 0]];
        self::assertEquals($expected, (new DocumentReader())->read($project->path('requirements.yaml'), 'config'));
    }

    /**
     * @throws JsonException
     */
    public function testReadParsesEmptyYamlMappingAsObject(): void
    {
        $project = new ProjectDirectory();
        $project->put('local.yaml', "version: 1\nsource:\n  id: manual\n  uri: source.html\n  format: html\n  selector: p\n  options: {}\nitems: []\n");
        $document = (new DocumentReader())->read($project->path('local.yaml'), 'definition');
        self::assertInstanceOf(stdClass::class, $document->source);
        self::assertEquals(new stdClass(), $document->source->options);
    }

    /**
     * @throws JsonException
     */
    public function testReadValidatesAgainstNamedSchema(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($project->path('requirements.yaml') . ': schema validation failed: {"/":["The required properties (source) are missing"]}');
        (new DocumentReader())->read($project->path('requirements.yaml'), 'definition');
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsDocumentThatIsNotMapping(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('list.yaml', "- 1\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$file: schema validation failed: {\"/\":[\"The data (array) must match the type: object\"]}");
        (new DocumentReader())->read($file, 'definition');
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerMalformedYaml')]
    public function testReadRejectsMalformedYaml(string $yaml, string $message): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('malformed.yaml', $yaml);
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($message);
        (new DocumentReader())->read($file, 'definition');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedYaml(): array
    {
        return [
            'unclosed sequence' => ["version: [\n", 'Malformed inline YAML string'],
            'custom tag' => ["version: !custom 1\nsource: null\nitems: []\n", 'Tags support is not enabled'],
            'php object' => ["version: 1\nsource: null\nitems: []\nx: !php/object 'O:8:\"stdClass\":0:{}'\n", 'Object support when parsing a YAML file has been disabled'],
        ];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerMarkdownFiles')]
    public function testReadDispatchesMarkdownFiles(string $file): void
    {
        $project = new ProjectDirectory();
        $path = $project->put($file, "---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\nThe converter shall uppercase letters.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$path: Markdown definitions require markdown.experimental: true.");
        (new DocumentReader())->read($path, 'definition');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerMarkdownFiles(): array
    {
        return [
            'md' => ['definition.md'],
            'markdown' => ['definition.markdown'],
            'upper case' => ['definition.MD'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testReadParsesMarkdownDefinitionWithOptions(): void
    {
        $project = new ProjectDirectory();
        $path = $project->put('definition.md', "---\nversion: 1\nsource:\n  id: manual\n  uri: source.html\n  format: html\n  selector: main p\n---\n\n# SPEC-001\n\nWhen a name is read, the parser shall require a leading letter.\n\n**evidence**\n\n- **selector:** #a\n\n  > Names shall start with a letter.\n");
        $reader = new DocumentReader();
        $expected = (object) [
            'version' => 1,
            'source' => (object) ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'],
            'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'When a name is read, the parser shall require a leading letter.', 'evidence' => [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']]]],
        ];
        self::assertEquals($expected, $reader->read($path, 'definition', ['experimental' => true], $project->directory));
        self::assertSame([], $reader->markdown->references());
    }

    /**
     * @throws JsonException
     */
    public function testReadValidatesMarkdownAgainstDefinitionSchema(): void
    {
        $project = new ProjectDirectory();
        $path = $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\nThe converter shall uppercase letters.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$path: schema validation failed:");
        (new DocumentReader())->read($path, 'config', ['experimental' => true]);
    }

    public function testMarkdownIsTheGivenDocument(): void
    {
        $markdown = new MarkdownDocument();
        self::assertSame($markdown, (new DocumentReader($markdown))->markdown);
    }

    #[DataProvider('providerPaths')]
    public function testIsMarkdownRecognizesExtensions(string $path, bool $expected): void
    {
        self::assertSame($expected, DocumentReader::isMarkdown($path));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function providerPaths(): array
    {
        return [
            'md' => ['definition.md', true],
            'markdown' => ['docs/definition.markdown', true],
            'upper case md' => ['DEFINITION.MD', true],
            'mixed case markdown' => ['definition.MarkDown', true],
            'yaml' => ['definition.yaml', false],
            'yml' => ['definition.yml', false],
            'json' => ['definition.json', false],
            'no extension' => ['md', false],
            'md in the directory only' => ['docs.md/definition.yaml', false],
            'md before the extension' => ['definition.md.yaml', false],
            'suffix without dot' => ['definitionmd', false],
            'longer extension' => ['definition.mdx', false],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testMappingConvertsNestedObjectsToArrays(): void
    {
        $document = (object) ['version' => 1, 'source' => (object) ['id' => 'manual', 'options' => new stdClass()], 'items' => [(object) ['id' => 'SPEC-001']]];
        self::assertSame(['version' => 1, 'source' => ['id' => 'manual', 'options' => []], 'items' => [['id' => 'SPEC-001']]], DocumentReader::mapping($document, 'definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testMappingAcceptsEmptyDocument(): void
    {
        self::assertSame([], DocumentReader::mapping(new stdClass(), 'definition.yaml'));
    }

    /**
     * @throws JsonException
     */
    public function testMappingRejectsNumericKeys(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('definition.yaml must have string keys.');
        DocumentReader::mapping((object) ['first', 'second'], 'definition.yaml');
    }

    /**
     * @throws JsonException
     */
    public function testMappingRejectsUnencodableValues(): void
    {
        $this->expectException(JsonException::class);
        DocumentReader::mapping((object) ['value' => NAN], 'definition.yaml');
    }

    /**
     * @throws JsonException
     */
    public function testMappingRejectsDeepDocuments(): void
    {
        $this->expectException(JsonException::class);
        DocumentReader::mapping((object) ['value' => json_decode(str_repeat('[', 600) . str_repeat(']', 600), false, 1000)], 'definition.yaml');
    }

    #[DataProvider('providerYaml')]
    public function testYamlWritesFormattedDocument(mixed $data, string $expected): void
    {
        self::assertSame($expected, DocumentReader::yaml($data));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function providerYaml(): array
    {
        return [
            'multi-line text as literal block' => [['statement' => "First line.\nSecond line.\n"], "statement: |\n  First line.\n  Second line.\n"],
            'object as mapping' => [(object) ['source' => (object) ['id' => 'manual']], "source:\n  id: manual\n"],
            'empty list as sequence' => [['items' => []], "items: []\n"],
            'two space indentation' => [['a' => ['b' => ['c' => 1]]], "a:\n  b:\n    c: 1\n"],
            'nesting kept inline only after twelve levels' => [['1' => ['2' => ['3' => ['4' => ['5' => ['6' => ['7' => ['8' => ['9' => ['10' => ['11' => ['12' => ['13' => 1]]]]]]]]]]]]], "1:\n  2:\n    3:\n      4:\n        5:\n          6:\n            7:\n              8:\n                9:\n                  10:\n                    11:\n                      12: { 13: 1 }\n"],
            'scalar' => ['text', 'text'],
        ];
    }
}
