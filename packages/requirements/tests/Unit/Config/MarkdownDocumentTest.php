<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use JsonException;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
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
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\FieldReader;
use Requirements\Config\Markdown\FieldSections;
use Requirements\Config\Markdown\Frontmatter;
use Requirements\Config\Markdown\LocalPath;
use Requirements\Config\Markdown\MetadataReader;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Profile\AllowedBlocks;
use Requirements\Config\Markdown\Profile\BlockKind;
use Requirements\Config\Markdown\Profile\DocumentSchema;
use Requirements\Config\Markdown\Profile\Occurrences;
use Requirements\Config\Markdown\Profile\SectionBlocks;
use Requirements\Config\Markdown\Profile\TextConstraint;
use Requirements\Config\Markdown\Quotation;
use Requirements\Config\Markdown\QuotationBlocks;
use Requirements\Config\Markdown\Reference;
use Requirements\Config\Markdown\Render\CardWriter;
use Requirements\Config\Markdown\Render\FieldWriter;
use Requirements\Config\Markdown\Render\MetadataWriter;
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
use Requirements\Report\Analysis;
use Requirements\Report\Analyzer;
use Requirements\Report\Claims;
use Requirements\Report\EvidenceMatcher;
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
use Requirements\Test\Registry as RunnerRegistry;
use stdClass;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Fake\GrammarCards;
use Tests\Fake\ProjectDirectory;

#[CoversClass(MarkdownDocument::class)]
#[UsesClass(CoverageThresholds::class)]
#[UsesClass(DefinitionReader::class)]
#[UsesClass(Definitions::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(ExtensionClasses::class)]
#[UsesClass(JsonSchemaFile::class)]
#[UsesClass(LinkValidator::class)]
#[UsesClass(Loader::class)]
#[UsesClass(Badges::class)]
#[UsesClass(CardReader::class)]
#[UsesClass(Citation::class)]
#[UsesClass(FieldReader::class)]
#[UsesClass(FieldSections::class)]
#[UsesClass(Frontmatter::class)]
#[UsesClass(LocalPath::class)]
#[UsesClass(MetadataReader::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(AllowedBlocks::class)]
#[UsesClass(BlockKind::class)]
#[UsesClass(DocumentSchema::class)]
#[UsesClass(Occurrences::class)]
#[UsesClass(SectionBlocks::class)]
#[UsesClass(TextConstraint::class)]
#[UsesClass(Quotation::class)]
#[UsesClass(QuotationBlocks::class)]
#[UsesClass(Reference::class)]
#[UsesClass(CardWriter::class)]
#[UsesClass(FieldWriter::class)]
#[UsesClass(MetadataWriter::class)]
#[UsesClass(Record::class)]
#[UsesClass(StaticBadge::class)]
#[UsesClass(SchemaValidator::class)]
#[UsesClass(Formatter::class)]
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
#[UsesClass(Analysis::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Claims::class)]
#[UsesClass(EvidenceMatcher::class)]
#[UsesClass(SourceUnit::class)]
#[UsesClass(UnitCollector::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(Unit::class)]
#[UsesClass(SourceRegistry::class)]
#[UsesClass(RunnerRegistry::class)]
#[Medium]
final class MarkdownDocumentTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testReadSharesTheYamlModelAndRenderPreservesLinksAndBadges(): void
    {
        $project = new ProjectDirectory();
        $yaml = (new Loader())->load($project->path('requirements.yaml'));
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md', 'reference.yaml'], 'markdown' => ['experimental' => true]]);
        $project->write('reference.yaml', ['version' => 1, 'source' => ['id' => 'reference', 'uri' => 'source.html', 'format' => 'html', 'selector' => '#b'], 'items' => [['id' => 'REQ-001', 'kind' => 'requirement', 'statement' => 'Names may contain digits.', 'evidence' => [['selector' => '#b', 'quote' => 'Names may contain digits.']]]]]);
        $project->put('definition.md', <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# SPEC-001

When a name is read, the parser shall require a leading letter.

**evidence**

- **selector:** #a

  > Names shall start with a letter.

**requirements**

- [REQ-001](reference.yaml#req-001)

**labels**

![grammar](assets/grammar.svg) ![strictness](https://example.invalid/badge.svg)

**metadata**

- **owner:** Parser team
- **reviewed:** true
- **priority:** 2
- **text:** "true"
- **values:**
  - stable
  - 1

**design**

- [Parser design](https://example.org/design)
- Preserve the original source spelling.
MD);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $item = $loaded->items['SPEC-001'];
        self::assertSame($yaml->items['SPEC-001']->statement, $item->statement);
        self::assertSame($yaml->items['SPEC-001']->data['evidence'], $item->data['evidence']);
        self::assertSame(['REQ-001'], $item->requirements);
        self::assertSame(['grammar', 'strictness'], $item->labels);
        self::assertSame(['owner' => 'Parser team', 'reviewed' => true, 'priority' => 2, 'text' => 'true', 'values' => ['stable', 1]], $item->data['metadata']);
        $formatter = new Formatter();
        $formatter->format($loaded->files, false, $loaded->markdown);
        $formatted = $project->read('definition.md');
        self::assertStringContainsString('reference.yaml#req-001', $formatted);
        self::assertStringContainsString('assets/grammar.svg', $formatted);
        self::assertStringNotContainsString('```', $formatted);
        self::assertEquals($item->data, (new Loader())->load($project->path('requirements.yaml'))->items['SPEC-001']->data);
        self::assertSame([], $formatter->format($loaded->files, true, $loaded->markdown));
    }

    /**
     * @throws JsonException
     * @throws CommonMarkException
     */
    public function testReadMatchesTheYamlModelAndRenderShowsOnlyReaderFacingInformation(): void
    {
        $project = new ProjectDirectory();
        $yaml = (new Loader())->load(GrammarCards::yaml($project));
        $markdown = (new Loader())->load(GrammarCards::markdown($project));
        self::assertEquals(array_column($yaml->items, 'data', 'id'), array_column(array_intersect_key($markdown->items, $yaml->items), 'data', 'id'));
        self::assertSame([], (new Analyzer())->analyze($markdown)->errors);
        $text = $project->read('markdown/grammar.md');
        self::assertNotSame('', $text);
        $body = preg_replace('/\A---\n.*?\n---\n/s', '', $text);
        self::assertIsString($body);
        $html = (string) (new CommonMarkConverter())->convert($body);
        $document = new Crawler($html);
        self::assertSame(['requirement', 'lexical', 'grammar', 'unsupported'], $document->filter('img')->extract(['alt']));
        self::assertSame(['A name starts with a letter.', 'The generator produces C code.'], $document->filter('blockquote p')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame(['source.html#names', '#req-001', 'source.html#generation'], $document->filter('a')->extract(['href']));
        self::assertStringNotContainsString('selector:', $document->text());
        self::assertStringNotContainsString('**kind**', $body);
        self::assertStringNotContainsString('**evidence**', $body);
        self::assertStringNotContainsString('**labels**', $body);
        self::assertSame([], (new Formatter())->format($markdown->files, true, $markdown->markdown));
    }

    /**
     * @throws JsonException
     */
    public function testReadAcceptsTheProposedSelectorCommentAndReasonSpelling(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true]]);
        $project->put('definition.md', <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# GENERATOR-001

![unsupported](<https://img.shields.io/badge/status-unsupported-blue>)

The generator shall produce C code.

> <!-- **selector:** \#c -->
> The generator shall produce C code.

**unsupport reason**

This library reads grammars and does not generate C code.
MD);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame('unsupported', $loaded->items['GENERATOR-001']->status);
        self::assertSame('#c', $loaded->items['GENERATOR-001']->evidence[0]->selector);
        self::assertSame([], (new Analyzer())->analyze($loaded)->errors);
        (new Formatter())->format($loaded->files, false, $loaded->markdown);
        $formatted = $project->read('definition.md');
        self::assertStringContainsString('[Source](source.html#c)', $formatted);
        self::assertStringContainsString('**unsupported reason**', $formatted);
        self::assertStringContainsString('status-unsupported-blue', $formatted);
    }

    /**
     * @throws JsonException
     */
    public function testReadResolvesCitationsFromTheMarkdownFileAndKeepsQuotationsSeparate(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definitions/*.md'], 'markdown' => ['experimental' => true]]);
        $project->put('definitions/names.md', <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# SPEC-001

![lexical](../assets/lexical.svg "category")
![grammar](../assets/grammar.svg)

When a name is read, the parser shall require a leading letter.

> Names shall start with a letter.

[Names](../source.html#a)

> Names may contain digits.
>
> [Digits](../source.html#:~:text=Names%20may%20contain%20digits.)
MD);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        $item = $loaded->items['SPEC-001'];
        self::assertSame('lexical', $item->category);
        self::assertSame(['grammar'], $item->labels);
        self::assertCount(2, $item->evidence);
        self::assertSame('#a', $item->evidence[0]->selector);
        self::assertSame('#:~:text=Names%20may%20contain%20digits.', $item->evidence[1]->selector);
        self::assertSame([], (new Analyzer())->analyze($loaded)->errors);
        $formatter = new Formatter();
        $formatter->format($loaded->files, false, $loaded->markdown);
        $formatted = $project->read('definitions/names.md');
        self::assertStringContainsString('[Names](../source.html#a)', $formatted);
        self::assertStringContainsString('[Digits](../source.html#:~:text=Names%20may%20contain%20digits.)', $formatted);
        self::assertStringContainsString('![lexical](../assets/lexical.svg "category")', $formatted);
        self::assertEquals($item->data, (new Loader())->load($project->path('requirements.yaml'))->items['SPEC-001']->data);
        self::assertSame([], $formatter->format($loaded->files, true, $loaded->markdown));
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerInvalidStructure')]
    public function testReadRejectsInvalidStructureAndFields(string $body, string $message): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n" . $body);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new DocumentReader())->read($file, 'definition', ['experimental' => true]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerInvalidStructure(): array
    {
        return [
            'setext heading' => ["SPEC-001\n========\n\nThe reader shall emit a tree.\n", 'item headings must use ATX # ID syntax.'],
            'prose link' => ["# SPEC-001\n\nThe reader shall emit [a tree](design.md).\n", 'Unsupported inline Markdown'],
            'nested heading' => ["## SPEC-001\n\nThe reader shall emit a tree.\n", 'document-schema requires top-level item ID headings.'],
            'missing statement' => ["# SPEC-001\n\n**reason**\n\nAvoid silent data loss.\n", 'SPEC-001 needs a statement paragraph after its badges.'],
            'extra paragraph' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\nAnother paragraph.\n", 'SPEC-001 expects a bold field heading after its statement.'],
            'code fence' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n```yaml\norigin: original\n```\n", 'document-schema forbids unsupported blocks'],
            'unknown field' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**lables**\n\n- grammar\n", "Unknown Markdown field 'lables'."],
            'duplicate field' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**origin**\n\noriginal\n\n**origin**\n\noriginal\n", "SPEC-001 has duplicate field 'origin'."],
            'evidence without selector' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**evidence**\n\n> Original text.\n", 'SPEC-001.evidence: Expected a bullet list.'],
            'evidence without quotation' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**evidence**\n\n- **selector:** #a\n", 'Evidence must use a bold selector field followed by one block quotation.'],
            'ordered list' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**labels**\n\n1. grammar\n", 'document-schema forbids ordered-list blocks'],
            'reference without link' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**requirements**\n\n- REQ-001\n", 'Expected one Markdown link with a nonempty destination.'],
            'empty label image' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**labels**\n\n![](badge.svg)\n", 'schema validation failed'],
            'nested code' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n**metadata**\n\n- **example:**\n\n  ```yaml\n  x: y\n  ```\n", 'document-schema forbids unsupported blocks'],
            'raw HTML' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\n<div>unsupported</div>\n", 'document-schema forbids unsupported blocks'],
        ];
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerMisleadingCards')]
    public function testReadRejectsAmbiguousOrMisleadingCardData(string $body, string $message): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\nversion: 1\nsource:\n  id: manual\n  uri: source.html\n  format: html\n  selector: main p\n---\n\n# SPEC-001\n\n" . $body);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new DocumentReader())->read($file, 'definition', ['experimental' => true]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMisleadingCards(): array
    {
        return [
            'badge without statement' => ['![requirement](https://img.shields.io/badge/kind-requirement-blue)', 'SPEC-001 needs a statement paragraph after its badges.'],
            'duplicate status' => ["![supported](a.svg \"status\") ![unsupported](b.svg \"status\")\n\nThe reader shall emit a tree.", "Duplicate 'status' badge or field."],
            'duplicate label' => ["![parser](a.svg) ![parser](b.svg)\n\nThe reader shall emit a tree.", 'Label badges must be unique.'],
            'badge field conflict' => ["![grammar](a.svg \"category\")\n\nThe reader shall emit a tree.\n\n**category**\n\nlexical", "SPEC-001 has duplicate field 'category'."],
            'misleading static badge' => ["![unsupported](https://img.shields.io/badge/status-supported-blue)\n\nThe reader shall emit a tree.", 'Static badge image text and role must agree with its alt text and title.'],
            'wrong hidden text directive' => ["The reader shall emit a tree.\n\n> <!-- selector: #:~:text=Different%20text. -->\n> Names shall start with a letter.", 'An exact Text Fragment selector must identify the complete quoted HTML unit.'],
            'unknown badge role' => ["![value](a.svg \"lable\")\n\nThe reader shall emit a tree.", 'A custom badge title must be kind, status, origin, category or label.'],
            'wrong citation resource' => ["The reader shall emit a tree.\n\n> <!-- selector: #a -->\n> Names shall start with a letter.\n\n[Source](different.html#a)", 'An evidence citation must link to this definition\'s source resource.'],
            'wrong citation anchor' => ["The reader shall emit a tree.\n\n> <!-- selector: #a -->\n> Names shall start with a letter.\n\n[Source](source.html#b)", 'The citation anchor disagrees with the evidence selector.'],
            'wrong citation text' => ["The reader shall emit a tree.\n\n> Names shall start with a letter.\n\n[Source](source.html#:~:text=Different%20text.)", 'An exact Text Fragment citation must identify the complete quoted HTML unit.'],
            'unsupported fragment range' => ["The reader shall emit a tree.\n\n> Names shall start with a letter.\n\n[Source](source.html#:~:text=Names,letter.)", 'Use one exact Text Fragment'],
            'quote with no locator' => ["The reader shall emit a tree.\n\n> Names shall start with a letter.", 'Evidence needs a selector comment or a source link identifying the quoted unit.'],
            'arbitrary comment' => ["The reader shall emit a tree.\n\n> <!-- unknown: value -->\n> Names shall start with a letter.", 'Only a selector comment is allowed at the start of an evidence quotation.'],
            'raw HTML in quote' => ["The reader shall emit a tree.\n\n> <div>Names shall start with a letter.</div>", 'Only a selector comment is allowed at the start of an evidence quotation.'],
            'comment outside quote' => ["The reader shall emit a tree.\n\n<!-- selector: #a -->", 'document-schema forbids unsupported blocks'],
            'two citations' => ["The reader shall emit a tree.\n\n> Names shall start with a letter.\n>\n> [Source](source.html#a)\n\n[Source](source.html#a)", 'An evidence quotation can have only one source citation.'],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testReadRequiresTheExperimentalOptIn(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('experimental');
        (new DocumentReader())->read('unread.md', 'definition');
    }

    /**
     * @param array<string, mixed> $options
     * @throws JsonException
     */
    #[DataProvider('providerWithoutOptIn')]
    public function testReadRejectsOptionsWithoutTheOptIn(array $options): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('unread.md: Markdown definitions require markdown.experimental: true.');
        (new MarkdownDocument())->read('unread.md', $options);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function providerWithoutOptIn(): array
    {
        return [
            'no options' => [[]],
            'disabled' => [['experimental' => false]],
            'truthy string' => [['experimental' => 'true']],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsABodyThatIsNotUtf8(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\nThe reader shall emit \xff.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unexpected encoding - UTF-8 or ASCII was expected');
        (new MarkdownDocument())->read($file, ['experimental' => true]);
    }

    /**
     * @throws JsonException
     */
    public function testReadRejectsUnknownMarkdownOptions(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("markdown: unknown field 'strict'.");
        (new MarkdownDocument())->read('unread.md', ['experimental' => true, 'strict' => true]);
    }

    /**
     * @throws JsonException
     */
    public function testReadReadsACardDefinitionWithoutASource(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definition.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\n![original](https://img.shields.io/badge/origin-original-blue)\n\nThe reader shall emit a tree.\n\n**rationale**\n\nKeep the tree.\n\n**related**\n\n- [REQ-001](other.md#req-001)\n");
        $markdown = new MarkdownDocument();
        self::assertEquals((object) ['version' => 1, 'source' => null, 'items' => [(object) ['id' => 'SPEC-001', 'origin' => 'original', 'statement' => 'The reader shall emit a tree.', 'reason' => 'Keep the tree.', 'related' => ['REQ-001']]]], $markdown->read($file, ['experimental' => true]));
    }

    /**
     * @throws JsonException
     */
    public function testReadResolvesTheSourceAgainstTheConfigurationDirectory(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definitions/names.md', "---\nversion: 1\nsource:\n  id: manual\n  uri: source.html\n  format: html\n  selector: 'main p'\n---\n\n# SPEC-001\n\nThe reader shall emit a tree.\n\n> Names shall start with a letter.\n\n[Names](../source.html#a)\n");
        $markdown = new MarkdownDocument();
        $data = $markdown->read($file, ['experimental' => true], $project->directory);
        self::assertEquals((object) ['version' => 1, 'source' => (object) ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall emit a tree.', 'evidence' => [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']]]]], $data);
        self::assertSame(file_get_contents($file), $markdown->render($data));
    }

    /**
     * @throws JsonException
     */
    public function testReadResolvesTheSourceAgainstTheFileDirectoryByDefault(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('definitions/names.md', "---\nversion: 1\nsource:\n  id: manual\n  uri: source.html\n  format: html\n  selector: 'main p'\n---\n\n# SPEC-001\n\nThe reader shall emit a tree.\n\n> Names shall start with a letter.\n\n[Names](../source.html#a)\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('An evidence citation must link to this definition\'s source resource.');
        (new MarkdownDocument())->read($file, ['experimental' => true]);
    }

    /**
     * @throws JsonException
     */
    public function testReferencesReturnsTheLinksOfTheLastRead(): void
    {
        $project = new ProjectDirectory();
        $first = $project->put('first.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\nThe reader shall emit a tree.\n\n**origin**\n\noriginal\n\n**reason**\n\nWhy.\n\n**requirements**\n\n- [REQ-001](reference.yaml#req-001)\n\n**related**\n\n- [SPEC-002](#spec-002)\n");
        $second = $project->put('second.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-003\n\nThe reader shall emit a tree.\n\n**related**\n\n- [SPEC-001](first.md#spec-001)\n");
        $markdown = new MarkdownDocument();
        self::assertSame([], $markdown->references());
        $markdown->read($first, ['experimental' => true]);
        self::assertEquals([new Reference('REQ-001', 'reference.yaml#req-001', $first), new Reference('SPEC-002', '#spec-002', $first)], $markdown->references());
        $markdown->read($second, ['experimental' => true]);
        self::assertEquals([new Reference('SPEC-001', 'first.md#spec-001', $second)], $markdown->references());
    }

    /**
     * @throws JsonException
     */
    public function testRenderKeepsEmptyListsAndStructuredMetadata(): void
    {
        $project = new ProjectDirectory();
        $data = (object) ['version' => 1, 'source' => null, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall preserve `shall` literally.', 'origin' => 'original', 'reason' => 'Keep keywords.', 'labels' => [], 'metadata' => (object) ['empty' => new stdClass(), 'array' => [], 'quote' => 'true', 'nested' => [(object) ['value' => null]], 'zero' => 0, 'entities' => 'Keep &copy; and <tokens> literally.', 'numbered' => '1. Entry', 'bullet' => '- Entry', 'lines' => "a\nb"]]]];
        $file = $project->path('definition.md');
        $markdown = new MarkdownDocument();
        file_put_contents($file, $markdown->render($data));
        self::assertEquals($data, $markdown->read($file, ['experimental' => true]));
    }

    /**
     * @throws JsonException
     */
    public function testRenderKeepsBadgeValuesWithSeparatorsAndUnicode(): void
    {
        $project = new ProjectDirectory();
        $data = (object) ['version' => 1, 'source' => null, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall preserve names.', 'origin' => 'original', 'reason' => 'Preserve names.', 'category' => '-names', 'labels' => ['-feature', 'under_score', 'with spaces', 'end-', '日本語']]]];
        $file = $project->path('definition.md');
        $markdown = new MarkdownDocument();
        file_put_contents($file, $markdown->render($data));
        self::assertEquals($data, $markdown->read($file, ['experimental' => true]));
    }

    /**
     * @throws JsonException
     */
    public function testRenderAddsTextFragmentNavigationForAComplexCssSelector(): void
    {
        $project = new ProjectDirectory();
        $project->write('requirements.yaml', ['version' => 1, 'definitions' => ['definition.md'], 'markdown' => ['experimental' => true]]);
        $project->put('definition.md', <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: main p
---

# SPEC-001

The parser shall require leading letters.

> <!-- selector: main > p:first-child -->
> Names shall start with a letter.
MD);
        $loaded = (new Loader())->load($project->path('requirements.yaml'));
        (new Formatter())->format($loaded->files, false, $loaded->markdown);
        $formatted = $project->read('definition.md');
        self::assertStringContainsString('[Source](source.html#:~:text=Names%20shall%20start%20with%20a%20letter.)', $formatted);
        $after = (new Loader())->load($project->path('requirements.yaml'));
        self::assertSame('main > p:first-child', $after->items['SPEC-001']->evidence[0]->selector);
        self::assertSame([], (new Analyzer())->analyze($after)->errors);
    }

    /**
     * @throws JsonException
     */
    public function testRenderWritesNewBadgesWithoutARead(): void
    {
        $data = (object) ['version' => 1, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall emit a tree.', 'labels' => ['grammar'], 'related' => ['REQ-001']]]];
        self::assertSame("---\nversion: 1\n---\n\n# SPEC-001\n\n![grammar](https://img.shields.io/badge/label-grammar-blue)\n\nThe reader shall emit a tree.\n\n**related**\n\n- [REQ-001](#req-001)\n", (new MarkdownDocument())->render($data));
    }

    /**
     * @throws JsonException
     */
    public function testRenderForgetsWhatAnEarlierReadRemembered(): void
    {
        $project = new ProjectDirectory();
        $first = $project->put('first.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\n![grammar](assets/grammar.svg)\n\nThe reader shall emit a tree.\n\n**related**\n\n- [REQ-001](other.md#req-001)\n");
        $second = $project->put('second.md', "---\nversion: 1\nsource:\n  id: manual\n  uri: source.html\n  format: html\n  selector: main p\n---\n\n# SPEC-002\n\nThe reader shall emit a tree.\n");
        $markdown = new MarkdownDocument();
        $markdown->read($first, ['experimental' => true]);
        $markdown->read($second, ['experimental' => true]);
        $data = (object) ['version' => 1, 'source' => null, 'items' => [(object) ['id' => 'SPEC-001', 'labels' => ['grammar'], 'statement' => 'The reader shall emit a tree.', 'evidence' => [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']], 'related' => ['REQ-001']]]];
        $third = $project->put('third.md', "---\nversion: 1\nsource: null\n---\n\n# SPEC-003\n\nThe reader shall emit a tree.\n");
        $markdown->read($third, ['experimental' => true]);
        self::assertSame("---\nversion: 1\nsource: null\n---\n\n# SPEC-001\n\n![grammar](https://img.shields.io/badge/label-grammar-blue)\n\nThe reader shall emit a tree.\n\n> <!-- selector: #a -->\n> Names shall start with a letter.\n\n**related**\n\n- [REQ-001](#req-001)\n", $markdown->render($data));
    }
}
