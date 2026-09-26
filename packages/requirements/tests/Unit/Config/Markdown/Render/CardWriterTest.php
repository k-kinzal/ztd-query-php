<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Render;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\DocumentReader;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\LocalPath;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Quotation;
use Requirements\Config\Markdown\Render\CardWriter;
use Requirements\Config\Markdown\Render\FieldWriter;
use Requirements\Config\Markdown\Render\MetadataWriter;
use Requirements\Config\Markdown\Render\Record;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;
use stdClass;

#[CoversClass(CardWriter::class)]
#[UsesClass(Badges::class)]
#[UsesClass(Citation::class)]
#[UsesClass(DocumentReader::class)]
#[UsesClass(Fields::class)]
#[UsesClass(FieldWriter::class)]
#[UsesClass(LocalPath::class)]
#[UsesClass(MetadataWriter::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Quotation::class)]
#[UsesClass(Record::class)]
#[UsesClass(Source::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(Unit::class)]
#[Small]
final class CardWriterTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testRenderWritesEveryFieldInTheCanonicalOrder(): void
    {
        $data = (object) ['version' => 1, 'source' => (object) ['id' => 'manual', 'uri' => 'source.html', 'format' => 'html', 'selector' => 'main p'], 'items' => [
            (object) ['id' => 'SPEC-001', 'statement' => 'The reader shall emit a tree.', 'status' => 'unsupported', 'reason' => 'Why.', 'kind' => 'specification', 'labels' => ['grammar'], 'category' => 'lexical', 'evidence' => [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']], 'requirements' => ['REQ-001'], 'design' => [(object) ['text' => 'Keep spelling.']], 'tests' => [], 'related' => ['SPEC-003'], 'metadata' => (object) ['owner' => 'Parser team']],
            (object) ['id' => 'SPEC-002', 'statement' => '1. The reader', 'origin' => 'original', 'reason' => 'Why.', 'labels' => [], 'evidence' => []],
        ]];
        $expected = <<<'MD'
---
version: 1
source:
  id: manual
  uri: source.html
  format: html
  selector: 'main p'
---

# SPEC-001

![specification](https://img.shields.io/badge/kind-specification-blue)
![unsupported](https://img.shields.io/badge/status-unsupported-orange)
![lexical](https://img.shields.io/badge/category-lexical-blue)
![grammar](https://img.shields.io/badge/label-grammar-blue)

The reader shall emit a tree.

> <!-- selector: #a -->
> Names shall start with a letter.

**unsupported reason**

Why.

**requirements**

- [REQ-001](#req-001)

**tests**

None.

**related**

- [SPEC-003](#spec-003)

**design**

- Keep spelling.

**metadata**

- **owner:** Parser team

# SPEC-002

![original](https://img.shields.io/badge/origin-original-blue)

1\. The reader

**rationale**

Why.

**labels**

None.

**evidence**

None.

MD;
        self::assertSame($expected, (new CardWriter())->render($data, [], []));
        self::assertTrue(property_exists($data, 'items'));
    }

    /**
     * @throws JsonException
     */
    public function testRenderReusesTheLinksBadgesAndCitationsRead(): void
    {
        $data = (object) ['version' => 1, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall emit a tree.', 'category' => 'lexical', 'labels' => ['grammar'], 'evidence' => [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.'], (object) ['selector' => 'main > p', 'quote' => 'Names may contain digits.']], 'requirements' => ['REQ-001']]]];
        $links = ['SPEC-001' => ['requirements' => ['REQ-001' => 'reference.yaml#req-001']]];
        $badges = ['SPEC-001' => ['category' => ['lexical' => ['url' => '../assets/lexical.svg', 'title' => 'category']], 'label' => ['grammar' => ['url' => '../assets/grammar.svg', 'title' => null]]]];
        $citations = ['SPEC-001' => [['url' => '../source.html#a', 'label' => 'Names']]];
        $source = new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definitions/names.md', '/project');
        $expected = <<<'MD'
---
version: 1
---

# SPEC-001

![lexical](../assets/lexical.svg "category")
![grammar](../assets/grammar.svg)

The reader shall emit a tree.

> Names shall start with a letter.

[Names](../source.html#a)

> <!-- selector: main &gt; p -->
> Names may contain digits.

[Source](../source.html#:~:text=Names%20may%20contain%20digits.)

**requirements**

- [REQ-001](reference.yaml#req-001)

MD;
        self::assertSame($expected, (new CardWriter())->render($data, $links, $badges, $citations, $source));
    }

    /**
     * @throws JsonException
     */
    public function testRenderWritesADefinitionWithoutItems(): void
    {
        self::assertSame("---\nversion: 1\nsource: null\n---\n", (new CardWriter())->render((object) ['version' => 1, 'source' => null, 'items' => []], [], []));
    }

    /**
     * @throws JsonException
     */
    public function testRenderEscapesTheStatement(): void
    {
        $data = (object) ['version' => 1, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall preserve `shall` literally.']]];
        self::assertSame("---\nversion: 1\n---\n\n# SPEC-001\n\nThe reader shall preserve \\`shall\\` literally.\n", (new CardWriter())->render($data, [], []));
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerMalformedDefinitions')]
    public function testRenderRejectsRecordsThatCannotBeCards(stdClass $data, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new CardWriter())->render($data, [], []);
    }

    /**
     * @return array<string, array{stdClass, string}>
     */
    public static function providerMalformedDefinitions(): array
    {
        return [
            'items not a list' => [(object) ['version' => 1, 'items' => (object) []], 'items must be a list.'],
            'item not a record' => [(object) ['version' => 1, 'items' => ['SPEC-001']], 'Expected a Markdown record.'],
            'item without ID' => [(object) ['version' => 1, 'items' => [(object) ['statement' => 'The reader shall emit a tree.']]], 'id must be a nonempty string.'],
            'item without statement' => [(object) ['version' => 1, 'items' => [(object) ['id' => 'SPEC-001']]], 'statement must be a nonempty string.'],
            'labels not strings' => [(object) ['version' => 1, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'S.', 'labels' => [1]]]], 'labels must contain nonempty strings.'],
            'evidence not a record' => [(object) ['version' => 1, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'S.', 'evidence' => ['#a']]]], 'Expected a Markdown record.'],
            'kind not a string' => [(object) ['version' => 1, 'items' => [(object) ['id' => 'SPEC-001', 'statement' => 'S.', 'kind' => 1]]], 'kind must be a nonempty string.'],
        ];
    }
}
