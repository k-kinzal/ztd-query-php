<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Badges;
use Requirements\Config\Markdown\CardReader;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\FieldReader;
use Requirements\Config\Markdown\FieldSections;
use Requirements\Config\Markdown\LocalPath;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Presentation;
use Requirements\Config\Markdown\Quotation;
use Requirements\Config\Markdown\QuotationBlocks;
use Requirements\Config\Markdown\Reference;
use Requirements\Config\Markdown\StaticBadge;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;
use Tests\Fake\MarkdownNodes;

#[CoversClass(CardReader::class)]
#[UsesClass(Badges::class)]
#[UsesClass(Citation::class)]
#[UsesClass(FieldReader::class)]
#[UsesClass(FieldSections::class)]
#[UsesClass(LocalPath::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Presentation::class)]
#[UsesClass(Quotation::class)]
#[UsesClass(QuotationBlocks::class)]
#[UsesClass(Reference::class)]
#[UsesClass(Source::class)]
#[UsesClass(StaticBadge::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(Unit::class)]
#[Small]
final class CardReaderTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    public function testCardsReadsEveryCard(): void
    {
        $body = "\n# REQ-001\n\n![requirement](https://img.shields.io/badge/kind-requirement-blue)\n\nA name starts with a letter.\n\n> A name starts with a letter.\n\n[Source](source.html#a)\n\n   # SPEC-001\n\nWhen a name is read, the parser shall require a leading letter.\n\n**requirements**\n\n- [REQ-001](#req-001)\n";
        $presentation = new Presentation();
        $items = (new CardReader($presentation, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project')))->cards(MarkdownNodes::parse($body), $body, 'definition.md');
        self::assertEquals([
            (object) ['id' => 'REQ-001', 'kind' => 'requirement', 'statement' => 'A name starts with a letter.', 'evidence' => [(object) ['selector' => '#a', 'quote' => 'A name starts with a letter.']]],
            (object) ['id' => 'SPEC-001', 'statement' => 'When a name is read, the parser shall require a leading letter.', 'requirements' => ['REQ-001']],
        ], $items);
        self::assertSame(['REQ-001' => ['kind' => ['requirement' => ['url' => 'https://img.shields.io/badge/kind-requirement-blue', 'title' => null]]], 'SPEC-001' => []], $presentation->badges);
        self::assertSame(['REQ-001' => [['url' => 'source.html#a', 'label' => 'Source']]], $presentation->citations);
        self::assertSame(['SPEC-001' => ['requirements' => ['REQ-001' => '#req-001']]], $presentation->links);
    }

    /**
     * @throws CommonMarkException
     */
    public function testCardsReadsNothingFromABodyWithoutHeadings(): void
    {
        self::assertSame([], (new CardReader(new Presentation(), null))->cards(MarkdownNodes::parse(''), '', 'definition.md'));
    }

    /**
     * @throws CommonMarkException
     */
    public function testCardsIgnoresBlocksBeforeTheFirstHeading(): void
    {
        $body = "Introduction.\n\n# SPEC-001\n\nThe reader shall emit a tree.\n";
        self::assertEquals([(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall emit a tree.']], (new CardReader(new Presentation(), null))->cards(MarkdownNodes::parse($body), $body, 'definition.md'));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerNotAtxHeadings')]
    public function testCardsRejectsHeadingsNotWrittenAsAtx(string $body): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('definition.md: item headings must use ATX # ID syntax.');
        (new CardReader(new Presentation(), null))->cards(MarkdownNodes::parse($body), $body, 'definition.md');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerNotAtxHeadings(): array
    {
        return [
            'setext heading' => ["SPEC-001\n========\n\nThe reader shall emit a tree.\n"],
            'second level heading' => ["## SPEC-001\n\nThe reader shall emit a tree.\n"],
            'second setext heading' => ["# SPEC-001\n\nThe reader shall emit a tree.\n\nSPEC-002\n--------\n\nThe reader shall emit a tree.\n"],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testCardsAcceptsATabAfterTheHash(): void
    {
        $body = "#\tSPEC-001\n\nThe reader shall emit a tree.\n";
        self::assertEquals([(object) ['id' => 'SPEC-001', 'statement' => 'The reader shall emit a tree.']], (new CardReader(new Presentation(), null))->cards(MarkdownNodes::parse($body), $body, 'definition.md'));
    }

    /**
     * @throws CommonMarkException
     */
    public function testCardReadsBadgesStatementEvidenceAndFields(): void
    {
        $blocks = MarkdownNodes::blocks("![lexical](../assets/lexical.svg \"category\")\n\n![grammar](../assets/grammar.svg)\n\nWhen a name is read,\n   the parser shall require a `leading` letter.\n\n> <!-- selector: #a -->\n> Names shall start with a letter.\n\n**origin**\n\nsourced\n");
        $heading = MarkdownNodes::first('# SPEC-001');
        self::assertInstanceOf(Heading::class, $heading);
        $presentation = new Presentation();
        $item = (new CardReader($presentation, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definitions/names.md', '/project')))->card($heading, $blocks, 'names.md');
        self::assertEquals((object) [
            'id' => 'SPEC-001',
            'category' => 'lexical',
            'labels' => ['grammar'],
            'statement' => 'When a name is read, the parser shall require a `leading` letter.',
            'evidence' => [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']],
            'origin' => 'sourced',
        ], $item);
        self::assertSame(['SPEC-001' => ['category' => ['lexical' => ['url' => '../assets/lexical.svg', 'title' => 'category']], 'label' => ['grammar' => ['url' => '../assets/grammar.svg', 'title' => null]]]], $presentation->badges);
        self::assertSame(['SPEC-001' => [null]], $presentation->citations);
    }

    /**
     * @throws CommonMarkException
     */
    public function testCardLeavesOutEvidenceWhenThereIsNoQuotation(): void
    {
        $heading = MarkdownNodes::first('# SPEC-001');
        self::assertInstanceOf(Heading::class, $heading);
        $item = (new CardReader(new Presentation(), null))->card($heading, MarkdownNodes::blocks('The reader shall emit a tree.'), 'definition.md');
        self::assertFalse(property_exists($item, 'evidence'));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerCardsWithoutStatements')]
    public function testCardRejectsACardWithoutAStatement(string $markdown): void
    {
        $heading = MarkdownNodes::first('# SPEC-001');
        self::assertInstanceOf(Heading::class, $heading);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('definition.md: SPEC-001 needs a statement paragraph after its badges.');
        (new CardReader(new Presentation(), null))->card($heading, MarkdownNodes::blocks($markdown), 'definition.md');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerCardsWithoutStatements(): array
    {
        return [
            'nothing' => [''],
            'badge without statement' => ['![requirement](https://img.shields.io/badge/kind-requirement-blue)'],
            'field instead of statement' => ["**reason**\n\nAvoid silent data loss.\n"],
            'list instead of statement' => ['- The reader shall emit a tree.'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testCardRejectsInlineMarkdownInTheStatement(): void
    {
        $heading = MarkdownNodes::first('# SPEC-001');
        self::assertInstanceOf(Heading::class, $heading);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unsupported inline Markdown');
        (new CardReader(new Presentation(), null))->card($heading, MarkdownNodes::blocks('The reader shall emit [a tree](design.md).'), 'definition.md');
    }
}
