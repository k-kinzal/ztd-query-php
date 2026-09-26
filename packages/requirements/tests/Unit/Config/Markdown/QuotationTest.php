<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\LocalPath;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Quotation;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;
use Tests\Fake\MarkdownNodes;

#[CoversClass(Quotation::class)]
#[UsesClass(Citation::class)]
#[UsesClass(LocalPath::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Source::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(Unit::class)]
#[Small]
final class QuotationTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    public function testReadReadsASelectorCommentAndTheQuotedText(): void
    {
        $quote = MarkdownNodes::first("> <!-- selector: #a -->\n> Names shall start\n> with a letter.\n>\n> Names may contain *digits*.\n");
        self::assertInstanceOf(BlockQuote::class, $quote);
        $quotation = new Quotation();
        $evidence = $quotation->read($quote, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project'));
        self::assertEquals((object) ['selector' => '#a', 'quote' => "Names shall start\nwith a letter.\n\nNames may contain digits."], $evidence);
        self::assertNull($quotation->link);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadDerivesTheSelectorFromTheLinkInsideTheQuotation(): void
    {
        $quote = MarkdownNodes::first("> Names may contain digits.\n>\n> [Digits](../source.html#:~:text=Names%20may%20contain%20digits.)\n");
        self::assertInstanceOf(BlockQuote::class, $quote);
        $quotation = new Quotation();
        $evidence = $quotation->read($quote, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definitions/names.md', '/project'));
        self::assertEquals((object) ['selector' => '#:~:text=Names%20may%20contain%20digits.', 'quote' => 'Names may contain digits.'], $evidence);
        self::assertSame(['url' => '../source.html#:~:text=Names%20may%20contain%20digits.', 'label' => 'Digits'], $quotation->link);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadDerivesTheSelectorFromTheAttribution(): void
    {
        $quote = MarkdownNodes::first('> Names shall start with a letter.');
        $attribution = MarkdownNodes::first('[Names](source.html#a)')->firstChild();
        self::assertInstanceOf(BlockQuote::class, $quote);
        self::assertInstanceOf(Link::class, $attribution);
        $quotation = new Quotation();
        $evidence = $quotation->read($quote, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project'), $attribution);
        self::assertEquals((object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.'], $evidence);
        self::assertSame(['url' => 'source.html#a', 'label' => 'Names'], $quotation->link);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadKeepsASelectorCommentThatTheLinkAgreesWith(): void
    {
        $quote = MarkdownNodes::first("> <!-- selector: main > p:first-child -->\n> Names shall start with a letter.\n>\n> [Source](source.html#:~:text=Names%20shall%20start%20with%20a%20letter.)\n");
        self::assertInstanceOf(BlockQuote::class, $quote);
        $evidence = (new Quotation())->read($quote, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project'));
        self::assertEquals((object) ['selector' => 'main > p:first-child', 'quote' => 'Names shall start with a letter.'], $evidence);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRejectsAQuotationWithoutADeclaredSource(): void
    {
        $quote = MarkdownNodes::first("> <!-- selector: #a -->\n> Names shall start with a letter.\n");
        self::assertInstanceOf(BlockQuote::class, $quote);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('A quotation needs a declared source.');
        (new Quotation())->read($quote, null);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRejectsASecondSourceCitation(): void
    {
        $quote = MarkdownNodes::first("> Names shall start with a letter.\n>\n> [Source](source.html#a)\n");
        $attribution = MarkdownNodes::first('[Source](source.html#a)')->firstChild();
        self::assertInstanceOf(BlockQuote::class, $quote);
        self::assertInstanceOf(Link::class, $attribution);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('An evidence quotation can have only one source citation.');
        (new Quotation())->read($quote, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project'), $attribution);
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedQuotations')]
    public function testReadRejectsMalformedQuotations(string $markdown, string $message): void
    {
        $quote = MarkdownNodes::first($markdown);
        self::assertInstanceOf(BlockQuote::class, $quote);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Quotation())->read($quote, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedQuotations(): array
    {
        return [
            'quote with no locator' => ['> Names shall start with a letter.', 'Evidence needs a selector comment or a source link identifying the quoted unit.'],
            'blank selector comment' => ["> <!-- selector: -->\n> Names shall start with a letter.\n", 'Evidence needs a selector comment or a source link identifying the quoted unit.'],
            'selector comment without text' => ['> <!-- selector: #a -->', 'Evidence needs quoted source text.'],
            'link without text' => ['> [Source](source.html#a)', 'Evidence needs quoted source text.'],
            'raw HTML after the text' => ["> Names shall start with a letter.\n>\n> <div>Names</div>\n", 'A quotation contains a selector comment, quoted prose, then an optional source link.'],
            'list in the quotation' => ["> <!-- selector: #a -->\n> - Names shall start with a letter.\n", 'A quotation contains a selector comment, quoted prose, then an optional source link.'],
            'arbitrary comment' => ["> <!-- unknown: value -->\n> Names shall start with a letter.\n", 'Only a selector comment is allowed at the start of an evidence quotation.'],
            'raw HTML' => ['> <div>Names shall start with a letter.</div>', 'Only a selector comment is allowed at the start of an evidence quotation.'],
            'link before the text' => ["> [Source](source.html#a)\n>\n> Names shall start with a letter.\n", 'Unsupported inline Markdown'],
            'link with more text' => ["> <!-- selector: #a -->\n> Names shall start with a letter.\n>\n> [Source](source.html#a) and more\n", 'Unsupported inline Markdown'],
            'link with an empty destination' => ["> Names shall start with a letter.\n>\n> [Source]()\n", 'Expected one Markdown link with a nonempty destination.'],
            'wrong citation resource' => ["> <!-- selector: #a -->\n> Names shall start with a letter.\n>\n> [Source](different.html#a)\n", 'An evidence citation must link to this definition\'s source resource.'],
            'wrong citation anchor' => ["> <!-- selector: #a -->\n> Names shall start with a letter.\n>\n> [Source](source.html#b)\n", 'The citation anchor disagrees with the evidence selector.'],
            'wrong hidden text directive' => ["> <!-- selector: #:~:text=Different%20text. -->\n> Names shall start with a letter.\n", 'An exact Text Fragment selector must identify the complete quoted HTML unit.'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerAnnotations')]
    public function testAnnotationReadsTheSelectorComment(string $markdown, string $expected): void
    {
        $comment = MarkdownNodes::first($markdown);
        self::assertInstanceOf(HtmlBlock::class, $comment);
        self::assertSame($expected, Quotation::annotation($comment));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerAnnotations(): array
    {
        return [
            'selector' => ['<!-- selector: #a -->', '#a'],
            'bold selector with an escaped hash' => ['<!-- **selector:** \\#c -->', '#c'],
            'no spaces' => ['<!--selector:#a-->', '#a'],
            'entities' => ['<!-- selector: main &gt; p:first-child -->', 'main > p:first-child'],
            'raw greater-than sign' => ['<!-- selector: main > p:first-child -->', 'main > p:first-child'],
            'trailing line break' => ["<!-- selector: #a -->\n", '#a'],
            'backslash inside' => ['<!-- selector: a\\#b -->', 'a\\#b'],
            'selector on the next line' => ["<!-- selector:\n#a -->", '#a'],
            'blank selector' => ['<!-- selector: -->', ' '],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerNotAnnotations')]
    public function testAnnotationRejectsAnythingButASelectorComment(string $markdown): void
    {
        $comment = MarkdownNodes::first($markdown);
        self::assertInstanceOf(HtmlBlock::class, $comment);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Only a selector comment is allowed at the start of an evidence quotation.');
        Quotation::annotation($comment);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerNotAnnotations(): array
    {
        return [
            'other comment' => ['<!-- unknown: value -->'],
            'element' => ['<div>selector: #a</div>'],
            'nested comment end' => ['<!-- selector: a --> -->'],
            'text before the comment' => ['<!-- x --><!-- selector: #a -->'],
        ];
    }

    /**
     * @param array{url: string, label: string}|null $link
     */
    #[DataProvider('providerRenderedQuotations')]
    public function testRenderWritesTheQuotation(string $uri, string $format, string $selector, string $quote, ?array $link, string $expected): void
    {
        self::assertSame($expected, Quotation::render($selector, $quote, new Citation(new Source('manual', $uri, $format, 'main p'), '/project/definition.md', '/project'), $link));
    }

    /**
     * @return array<string, array{string, string, string, string, array{url: string, label: string}|null, string}>
     */
    public static function providerRenderedQuotations(): array
    {
        return [
            'element ID' => ['source.html', 'html', '#a', 'Names shall start with a letter.', null, "> Names shall start with a letter.\n\n[Source](source.html#a)"],
            'CSS selector' => ['source.html', 'html', 'main > p:first-child', 'Names shall start with a letter.', null, "> <!-- selector: main &gt; p:first-child -->\n> Names shall start with a letter.\n\n[Source](source.html#:~:text=Names%20shall%20start%20with%20a%20letter.)"],
            'read link' => ['source.html', 'html', '#a', 'Names shall start with a letter.', ['url' => 'source.html#a', 'label' => 'Names'], "> Names shall start with a letter.\n\n[Names](source.html#a)"],
            'read link disagreeing with the selector' => ['source.html', 'html', 'main p', 'Names shall start with a letter.', ['url' => 'source.html#a', 'label' => 'Names'], "> <!-- selector: main p -->\n> Names shall start with a letter.\n\n[Names](source.html#a)"],
            'read link to another resource' => ['source.html', 'html', '#a', 'Names shall start with a letter.', ['url' => 'other.html#a', 'label' => 'Names'], "> <!-- selector: #a -->\n> Names shall start with a letter.\n\n[Names](other.html#a)"],
            'JSON selector' => ['source.json', 'json', '$.rules[0].text', 'First rule.', null, "> <!-- selector: $.rules[0].text -->\n> First rule.\n\n[Source](source.json)"],
            'paragraphs and escapes' => ['source.html', 'html', '#a', "Names *shall*\n\n- start", null, "> Names \\*shall\\*\n> \n> \\- start\n\n[Source](source.html#a)"],
            'label and destination escapes' => ['my source.html', 'html', '#a', 'Names.', ['url' => 'my source.html#a', 'label' => '[Names]'], "> Names.\n\n[\\[Names\\]](<my%20source.html#a>)"],
        ];
    }

    public function testRenderWritesASelectorCommentWithoutACitation(): void
    {
        self::assertSame("> <!-- selector: #a -->\n> Names shall start with a letter.", Quotation::render('#a', 'Names shall start with a letter.', null, null));
    }

    public function testRenderKeepsTheReadLinkWithoutACitation(): void
    {
        self::assertSame("> <!-- selector: #a -->\n> Names shall start with a letter.\n\n[Names](source.html#a)", Quotation::render('#a', 'Names shall start with a letter.', null, ['url' => 'source.html#a', 'label' => 'Names']));
    }

    public function testRenderEscapesTheSelectorComment(): void
    {
        self::assertSame("> <!-- selector: p[title=\"a&amp;b\"] &lt;x&gt; -->\n> Names.", Quotation::render('p[title="a&b"] <x>', 'Names.', null, null));
    }
}
