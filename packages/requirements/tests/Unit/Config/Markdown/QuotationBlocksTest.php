<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Citation;
use Requirements\Config\Markdown\LocalPath;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Presentation;
use Requirements\Config\Markdown\Quotation;
use Requirements\Config\Markdown\QuotationBlocks;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Source\TextFragment;
use Requirements\Source\Unit;
use Tests\Fake\MarkdownNodes;

#[CoversClass(QuotationBlocks::class)]
#[UsesClass(Citation::class)]
#[UsesClass(LocalPath::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Presentation::class)]
#[UsesClass(Quotation::class)]
#[UsesClass(Source::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(Unit::class)]
#[Small]
final class QuotationBlocksTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    public function testReadReadsTheLeadingQuotationsAndTheirAttributions(): void
    {
        $presentation = new Presentation();
        $blocks = MarkdownNodes::blocks("> Names shall start with a letter.\n\n[Names](../source.html#a)\n\n> Names may contain digits.\n>\n> [Digits](../source.html#:~:text=Names%20may%20contain%20digits.)\n\n> <!-- selector: #c -->\n> The generator shall produce C code.\n\n**reason**\n\nWhy.\n");
        [$evidence, $rest] = (new QuotationBlocks($presentation, new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definitions/names.md', '/project')))->read($blocks, 'names.md', 'SPEC-001');
        self::assertEquals([
            (object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.'],
            (object) ['selector' => '#:~:text=Names%20may%20contain%20digits.', 'quote' => 'Names may contain digits.'],
            (object) ['selector' => '#c', 'quote' => 'The generator shall produce C code.'],
        ], $evidence);
        self::assertSame(array_slice($blocks, 4), $rest);
        self::assertSame(['SPEC-001' => [
            ['url' => '../source.html#a', 'label' => 'Names'],
            ['url' => '../source.html#:~:text=Names%20may%20contain%20digits.', 'label' => 'Digits'],
            null,
        ]], $presentation->citations);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadLeavesBlocksWithoutQuotationsUnchanged(): void
    {
        $presentation = new Presentation();
        $blocks = MarkdownNodes::blocks("[Names](source.html#a)\n\n> Names shall start with a letter.\n");
        self::assertSame([[], $blocks], (new QuotationBlocks($presentation, null))->read($blocks, 'definition.md', 'SPEC-001'));
        self::assertSame([], $presentation->citations);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadLeavesAParagraphWithMoreThanALinkAfterTheQuotation(): void
    {
        $blocks = MarkdownNodes::blocks("> <!-- selector: #a -->\n> Names shall start with a letter.\n\n[Names](source.html#a) and more\n");
        [$evidence, $rest] = (new QuotationBlocks(new Presentation(), new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project')))->read($blocks, 'definition.md', 'SPEC-001');
        self::assertEquals([(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']], $evidence);
        self::assertSame([$blocks[1]], $rest);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadPrefixesTheErrorsWithTheFileAndItem(): void
    {
        $blocks = MarkdownNodes::blocks("> <!-- selector: #a -->\n> Names shall start with a letter.\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('definition.md: SPEC-001: A quotation needs a declared source.');
        (new QuotationBlocks(new Presentation(), null))->read($blocks, 'definition.md', 'SPEC-001');
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRejectsTwoSourceCitations(): void
    {
        $blocks = MarkdownNodes::blocks("> Names shall start with a letter.\n>\n> [Source](source.html#a)\n\n[Source](source.html#a)\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('definition.md: SPEC-001: An evidence quotation can have only one source citation.');
        (new QuotationBlocks(new Presentation(), new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project')))->read($blocks, 'definition.md', 'SPEC-001');
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRejectsAnAttributionWithAnEmptyDestination(): void
    {
        $blocks = MarkdownNodes::blocks("> Names shall start with a letter.\n\n[Source]()\n");
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Expected one Markdown link with a nonempty destination.');
        (new QuotationBlocks(new Presentation(), new Citation(new Source('manual', 'source.html', 'html', 'main p'), '/project/definition.md', '/project')))->read($blocks, 'definition.md', 'SPEC-001');
    }
}
