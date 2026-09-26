<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Nodes;
use Requirements\Input\InvalidInputException;
use Tests\Fake\MarkdownNodes;

#[CoversClass(Nodes::class)]
#[Small]
final class NodesTest extends TestCase
{
    public function testTextReturnsTheLiteralOfATextNode(): void
    {
        self::assertSame('plain', Nodes::text(new Text('plain')));
    }

    public function testTextReturnsTheCodeWithoutBackticks(): void
    {
        self::assertSame('shall', Nodes::text(new Code('shall')));
    }

    public function testTextKeepsTheBackticksOfCodeAsALiteral(): void
    {
        self::assertSame('`shall`', Nodes::text(new Code('shall'), true));
    }

    public function testTextReturnsALineBreakForANewline(): void
    {
        self::assertSame("\n", Nodes::text(new Newline(Newline::SOFTBREAK)));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerInlineText')]
    public function testTextJoinsTheInlineChildren(string $markdown, bool $literals, string $expected): void
    {
        self::assertSame($expected, Nodes::text(MarkdownNodes::first($markdown), $literals));
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function providerInlineText(): array
    {
        return [
            'emphasis and code' => ["a **b** _c_ `d`\ne", false, "a b c d\ne"],
            'code literal' => ["a **b** _c_ `d`\ne", true, "a b c `d`\ne"],
            'nested code literal' => ['**`shall`**', true, '`shall`'],
            'heading' => ['# SPEC-001', false, 'SPEC-001'],
            'escaped characters' => ['\\*not emphasis\\*', false, '*not emphasis*'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerUnsupportedInline')]
    public function testTextRejectsUnsupportedInlineMarkdown(string $markdown): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unsupported inline Markdown; use text, emphasis or code spans. Put links in reference or design fields.');
        Nodes::text(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnsupportedInline(): array
    {
        return [
            'link' => ['The reader shall emit [a tree](design.md).'],
            'image' => ['![grammar](grammar.svg)'],
            'inline HTML' => ['a <b>tree</b>'],
            'link inside emphasis' => ['*[a](b)*'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testItemsReturnsTheItemsOfABulletList(): void
    {
        $items = Nodes::items(MarkdownNodes::first("- a\n- b\n- c\n"));
        self::assertCount(3, $items);
        self::assertSame('b', Nodes::text(Nodes::paragraph($items[1])));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerNotBulletLists')]
    public function testItemsRejectsAnythingButABulletList(string $markdown): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Expected a bullet list.');
        Nodes::items(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerNotBulletLists(): array
    {
        return [
            'ordered list' => ['1. grammar'],
            'paragraph' => ['grammar'],
            'quotation' => ['> - grammar'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testParagraphReturnsTheSingleParagraphOfAListItem(): void
    {
        $items = Nodes::items(MarkdownNodes::first("- a *b*\n"));
        self::assertSame('a b', Nodes::text(Nodes::paragraph($items[0])));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerNotSingleParagraphs')]
    public function testParagraphRejectsAnItemWithoutASingleParagraph(string $markdown): void
    {
        $items = Nodes::items(MarkdownNodes::first($markdown));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Expected a single paragraph in this list item.');
        Nodes::paragraph($items[0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerNotSingleParagraphs(): array
    {
        return [
            'two paragraphs' => ["- a\n\n  b\n"],
            'paragraph and list' => ["- a\n  - b\n"],
            'quotation' => ["- > a\n"],
            'empty item' => ["-\n- b\n"],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testLinkReturnsTheSingleLinkOfAParagraph(): void
    {
        $link = Nodes::link(MarkdownNodes::first('[REQ-001](reference.yaml#req-001)'));
        self::assertSame('reference.yaml#req-001', $link->getUrl());
        self::assertSame('REQ-001', Nodes::text($link));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerNotSingleLinks')]
    public function testLinkRejectsAnythingButOneLinkWithADestination(string $markdown): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Expected one Markdown link with a nonempty destination.');
        Nodes::link(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerNotSingleLinks(): array
    {
        return [
            'text' => ['REQ-001'],
            'link and text' => ['[REQ-001](a.md) and more'],
            'text and link' => ['see [REQ-001](a.md)'],
            'empty destination' => ['[REQ-001]()'],
            'image' => ['![REQ-001](a.svg)'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerFieldHeadings')]
    public function testFieldReturnsTheLowercaseNameOfABoldHeading(string $markdown, ?string $expected): void
    {
        self::assertSame($expected, Nodes::field(MarkdownNodes::first($markdown)));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function providerFieldHeadings(): array
    {
        return [
            'field' => ['**tests**', 'tests'],
            'uppercase' => ['**Unsupported Reason**', 'unsupported reason'],
            'bold followed by text' => ['**runner:** target', null],
            'text followed by bold' => ['a **tests**', null],
            'emphasis' => ['*tests*', null],
            'plain paragraph' => ['tests', null],
            'bold heading' => ['# **tests**', null],
            'bullet list' => ['- **tests**', null],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testFieldRejectsUnsupportedInlineMarkdownInTheName(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unsupported inline Markdown');
        Nodes::field(MarkdownNodes::first('**[tests](a.md)**'));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerPairs')]
    public function testPairSplitsTheBoldNameAndItsValue(string $markdown, string $name, string $value): void
    {
        $paragraph = MarkdownNodes::first($markdown);
        self::assertInstanceOf(Paragraph::class, $paragraph);
        self::assertSame([$name, $value], Nodes::pair($paragraph));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function providerPairs(): array
    {
        return [
            'colon inside the bold name' => ['**runner:** target', 'runner', 'target'],
            'colon after the bold name' => ['**runner**: target', 'runner', 'target'],
            'spaced colon after the bold name' => ['**runner** :  target  ', 'runner', 'target'],
            'empty value' => ['**values:**', 'values', ''],
            'value with emphasis and code' => ['**owner:** *Parser* `team`', 'owner', 'Parser team'],
            'empty name' => ['**:** 1', '', '1'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerNotPairs')]
    public function testPairRejectsAParagraphWithoutABoldNameAndColon(string $markdown, string $message): void
    {
        $paragraph = MarkdownNodes::first($markdown);
        self::assertInstanceOf(Paragraph::class, $paragraph);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        Nodes::pair($paragraph);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerNotPairs(): array
    {
        return [
            'no bold name' => ['runner: target', 'Expected a bold field name followed by a colon.'],
            'no colon' => ['**runner** target', 'Expected a colon after the bold field name.'],
            'colon later' => ['**runner** target: x', 'Expected a colon after the bold field name.'],
        ];
    }

    #[DataProvider('providerEscapes')]
    public function testEscapeProtectsMarkdownSyntax(string $text, string $expected): void
    {
        self::assertSame($expected, Nodes::escape($text));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerEscapes(): array
    {
        return [
            'plain' => ['The reader shall emit a tree.', 'The reader shall emit a tree.'],
            'inline syntax' => ['\\ ` * _ [ ] < > & !', '\\\\ \\` \\* \\_ \\[ \\] \\< \\> \\& \\!'],
            'heading' => ['# title', '\\# title'],
            'setext underline' => ["a\n===", "a\n\\==="],
            'plus bullet' => ['+ entry', '\\+ entry'],
            'dash bullet' => ['- Entry', '\\- Entry'],
            'indented bullet' => ["a\n  - b", "a\n  \\- b"],
            'ordered entry' => ['1. Entry', '1\\. Entry'],
            'parenthesized ordered entry' => ['12) Entry', '12\\) Entry'],
            'number inside text' => ['Version 1. Entry', 'Version 1. Entry'],
            'dash inside text' => ['a - b', 'a - b'],
        ];
    }

    #[DataProvider('providerDestinations')]
    public function testDestinationBracketsDestinationsThatNeedIt(string $url, string $expected): void
    {
        self::assertSame($expected, Nodes::destination($url));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerDestinations(): array
    {
        return [
            'plain' => ['https://example.org/a.svg', 'https://example.org/a.svg'],
            'space' => ['my source.html', '<my%20source.html>'],
            'parentheses' => ['a(b).html', '<a(b).html>'],
            'angle brackets' => ['<a>', '<%3Ca%3E>'],
            'line feed' => ["a\nb", '<a%0Ab>'],
            'carriage return' => ["a\rb", '<a%0Db>'],
            'tab' => ["a\tb", "<a\tb>"],
        ];
    }

    #[DataProvider('providerAnchors')]
    public function testAnchorLowercasesAndDropsDots(string $id, string $expected): void
    {
        self::assertSame($expected, Nodes::anchor($id));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerAnchors(): array
    {
        return [
            'item ID' => ['REQ-001', 'req-001'],
            'dotted ID' => ['A.B.C-1', 'abc-1'],
        ];
    }
}
