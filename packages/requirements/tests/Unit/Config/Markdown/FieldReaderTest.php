<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use League\CommonMark\Exception\CommonMarkException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\FieldReader;
use Requirements\Config\Markdown\MetadataReader;
use Requirements\Config\Markdown\Nodes;
use Requirements\Input\InvalidInputException;
use Tests\Fake\MarkdownNodes;

#[CoversClass(FieldReader::class)]
#[UsesClass(MetadataReader::class)]
#[UsesClass(Nodes::class)]
#[Small]
final class FieldReaderTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerProseFields')]
    public function testReadReadsProseFields(string $name): void
    {
        self::assertSame("Avoid silent\ndata loss.\n\nKeep shall.", (new FieldReader())->read($name, MarkdownNodes::blocks("Avoid silent\ndata loss.\n\nKeep `shall`.\n")));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerProseFields(): array
    {
        return [
            'kind' => ['kind'],
            'status' => ['status'],
            'origin' => ['origin'],
            'category' => ['category'],
            'reason' => ['reason'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerListFields')]
    public function testReadReadsNoneAsAnEmptyList(string $name): void
    {
        self::assertSame([], (new FieldReader())->read($name, MarkdownNodes::blocks('None.')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerListFields(): array
    {
        return [
            'evidence' => ['evidence'],
            'tests' => ['tests'],
            'requirements' => ['requirements'],
            'related' => ['related'],
            'labels' => ['labels'],
            'design' => ['design'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadReadsNoneAsProseForAProseField(): void
    {
        self::assertSame('None.', (new FieldReader())->read('reason', MarkdownNodes::blocks('None.')));
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadReadsAListField(): void
    {
        self::assertEquals([(object) ['runner' => 'unit', 'target' => 'Sample\\PassingTest::testPass']], (new FieldReader())->read('tests', MarkdownNodes::blocks('- **unit:** Sample\\PassingTest::testPass')));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerListValues')]
    public function testReadReadsTheListOfAField(string $name, string $markdown, mixed $expected): void
    {
        self::assertEquals($expected, (new FieldReader())->read($name, MarkdownNodes::blocks($markdown)));
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public static function providerListValues(): array
    {
        return [
            'evidence' => ['evidence', "- **selector:** #a\n\n  > Names shall start with a letter.\n", [(object) ['selector' => '#a', 'quote' => 'Names shall start with a letter.']]],
            'requirements' => ['requirements', '- [REQ-001](reference.yaml#req-001)', ['REQ-001']],
            'related' => ['related', '- [SPEC-001](other.md#spec-001)', ['SPEC-001']],
            'labels' => ['labels', '![grammar](assets/grammar.svg)', ['grammar']],
            'design' => ['design', '- Preserve the original source spelling.', [(object) ['text' => 'Preserve the original source spelling.']]],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadReadsMetadata(): void
    {
        self::assertEquals((object) ['owner' => 'Parser team'], (new FieldReader())->read('metadata', MarkdownNodes::blocks('- **owner:** Parser team')));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedSections')]
    public function testReadRejectsMalformedSections(string $name, string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new FieldReader())->read($name, MarkdownNodes::blocks($markdown));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function providerMalformedSections(): array
    {
        return [
            'empty section' => ['reason', '', "Markdown field 'reason' needs a value."],
            'prose list' => ['origin', '- original', 'Expected prose paragraphs.'],
            'two lists' => ['tests', "- **unit:** a\n\n***\n\n- **unit:** b\n", "Markdown field 'tests' needs one list or badge paragraph."],
            'None followed by a list' => ['labels', "None.\n\n- grammar\n", "Markdown field 'labels' needs one list or badge paragraph."],
            'None for metadata' => ['metadata', 'None.', 'Metadata must be a list of bold keys and values.'],
            'None with emphasis' => ['labels', 'None. *really*', 'Use ![label](image-url) for each label badge.'],
            'lowercase none' => ['labels', 'none.', 'Use ![label](image-url) for each label badge.'],
            'unknown field' => ['lables', '- grammar', "Unknown Markdown field 'lables'."],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testProseJoinsParagraphsWithBlankLines(): void
    {
        self::assertSame("a\n\nb", (new FieldReader())->prose(MarkdownNodes::blocks("a\n\nb\n")));
    }

    /**
     * @throws CommonMarkException
     */
    public function testProseRejectsABlockThatIsNotAParagraph(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Expected prose paragraphs.');
        (new FieldReader())->prose(MarkdownNodes::blocks("a\n\n> b\n"));
    }

    /**
     * @throws CommonMarkException
     */
    public function testEvidenceReadsSelectorsAndQuotations(): void
    {
        $markdown = "- **selector:** #a\n\n  > Names shall start\n  > with a letter.\n  >\n  > Names may contain digits.\n\n- **selector:** main p\n\n  > The generator shall produce C code.\n";
        self::assertEquals([
            (object) ['selector' => '#a', 'quote' => "Names shall start\nwith a letter.\n\nNames may contain digits."],
            (object) ['selector' => 'main p', 'quote' => 'The generator shall produce C code.'],
        ], (new FieldReader())->evidence(MarkdownNodes::first($markdown)));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedEvidence')]
    public function testEvidenceRejectsMalformedEntries(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new FieldReader())->evidence(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedEvidence(): array
    {
        return [
            'quotation without a list' => ['> Original text.', 'Expected a bullet list.'],
            'quotation first' => ['- > Original text.', 'Evidence needs a selector and a block quotation.'],
            'empty entry' => ["-\n- **selector:** #a\n", 'Evidence needs a selector and a block quotation.'],
            'no quotation' => ['- **selector:** #a', 'Evidence must use a bold selector field followed by one block quotation.'],
            'other bold name' => ["- **quote:** #a\n\n  > Original text.\n", 'Evidence must use a bold selector field followed by one block quotation.'],
            'paragraph instead of quotation' => ["- **selector:** #a\n\n  Original text.\n", 'Evidence must use a bold selector field followed by one block quotation.'],
            'block after the quotation' => ["- **selector:** #a\n\n  > Original text.\n\n  More.\n", 'Evidence must use a bold selector field followed by one block quotation.'],
            'selector without bold name' => ["- selector: #a\n\n  > Original text.\n", 'Expected a bold field name followed by a colon.'],
            'list in the quotation' => ["- **selector:** #a\n\n  > - Original text.\n", 'Expected prose paragraphs.'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testTestsReadsRunnersAndTargets(): void
    {
        self::assertEquals([(object) ['runner' => 'unit', 'target' => 'Sample\\PassingTest::testPass'], (object) ['runner' => 'behat', 'target' => 'example.feature:2']], (new FieldReader())->tests(MarkdownNodes::first("- **unit:** Sample\\PassingTest::testPass\n- **behat**: example.feature:2\n")));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedTests')]
    public function testTestsRejectsEntriesWithoutABoldRunner(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new FieldReader())->tests(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedTests(): array
    {
        return [
            'plain entry' => ['- unit: Sample\\PassingTest::testPass', 'Expected a bold field name followed by a colon.'],
            'two paragraphs' => ["- **unit:** a\n\n  b\n", 'Expected a single paragraph in this list item.'],
            'ordered list' => ['1. **unit:** a', 'Expected a bullet list.'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testReferencesReadsLinkedIdsAndRemembersTheirTargets(): void
    {
        $reader = new FieldReader();
        self::assertSame(['REQ-001', 'SPEC-001'], $reader->references(MarkdownNodes::first("- [REQ-001](reference.yaml#req-001)\n- [SPEC-001](#spec-001)\n")));
        self::assertSame(['REQ-001' => 'reference.yaml#req-001', 'SPEC-001' => '#spec-001'], $reader->links);
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedReferences')]
    public function testReferencesRejectsEntriesThatAreNotASingleLink(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new FieldReader())->references(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedReferences(): array
    {
        return [
            'reference without link' => ['- REQ-001', 'Expected one Markdown link with a nonempty destination.'],
            'link and text' => ['- [REQ-001](a.md) and more', 'Expected one Markdown link with a nonempty destination.'],
            'link with an image' => ['- [![REQ-001](a.svg)](a.md)', 'Unsupported inline Markdown'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testLabelsReadsABulletList(): void
    {
        $reader = new FieldReader();
        self::assertSame(['grammar', 'strictness'], $reader->labels(MarkdownNodes::first("- grammar\n- *strictness*\n")));
        self::assertSame([], $reader->badges);
    }

    /**
     * @throws CommonMarkException
     */
    public function testLabelsReadsABadgeParagraphAndRemembersTheImages(): void
    {
        $reader = new FieldReader();
        self::assertSame(['grammar', 'strictness'], $reader->labels(MarkdownNodes::first("![grammar](assets/grammar.svg) ![strictness](https://example.invalid/badge.svg)\n")));
        self::assertSame(['grammar' => 'assets/grammar.svg', 'strictness' => 'https://example.invalid/badge.svg'], $reader->badges);
    }

    /**
     * @throws CommonMarkException
     */
    public function testLabelsReadsBadgesOnSeveralLines(): void
    {
        self::assertSame(['grammar', 'strictness'], (new FieldReader())->labels(MarkdownNodes::first("![grammar](grammar.svg)\n![strictness](strictness.svg)\n")));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedLabels')]
    public function testLabelsRejectsAnythingButLabelsOrBadges(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new FieldReader())->labels(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedLabels(): array
    {
        return [
            'quotation' => ['> grammar', 'Labels must be a bullet list or a paragraph of badge images.'],
            'ordered list' => ['1. grammar', 'Expected a bullet list.'],
            'text' => ['grammar', 'Use ![label](image-url) for each label badge.'],
            'text after a badge' => ['![grammar](grammar.svg) and more', 'Use ![label](image-url) for each label badge.'],
            'empty destination' => ['![grammar]()', 'Use ![label](image-url) for each label badge.'],
            'blank paragraph' => ['&#32;', 'The badge paragraph must contain labels.'],
            'list entry with a link' => ['- [grammar](a.md)', 'Unsupported inline Markdown'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testDesignReadsLinksAndText(): void
    {
        $markdown = "- [Parser design](https://example.org/design)\n- <https://example.org/plain>\n- Preserve the original *source* spelling.\n";
        self::assertEquals([
            (object) ['url' => 'https://example.org/design', 'text' => 'Parser design'],
            (object) ['url' => 'https://example.org/plain'],
            (object) ['text' => 'Preserve the original source spelling.'],
        ], (new FieldReader())->design(MarkdownNodes::first($markdown)));
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedDesign')]
    public function testDesignRejectsMalformedEntries(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new FieldReader())->design(MarkdownNodes::first($markdown));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedDesign(): array
    {
        return [
            'text with a link' => ['- See [design](https://example.org/design).', 'Unsupported inline Markdown'],
            'two paragraphs' => ["- a\n\n  b\n", 'Expected a single paragraph in this list item.'],
            'paragraph' => ['Preserve spelling.', 'Expected a bullet list.'],
        ];
    }
}
