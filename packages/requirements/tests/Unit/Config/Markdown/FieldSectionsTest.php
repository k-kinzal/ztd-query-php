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
use Requirements\Config\Markdown\FieldSections;
use Requirements\Config\Markdown\MetadataReader;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Presentation;
use Requirements\Config\Markdown\Reference;
use Requirements\Input\InvalidInputException;
use stdClass;
use Tests\Fake\MarkdownNodes;

#[CoversClass(FieldSections::class)]
#[UsesClass(FieldReader::class)]
#[UsesClass(MetadataReader::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Presentation::class)]
#[UsesClass(Reference::class)]
#[Small]
final class FieldSectionsTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    public function testReadReadsEveryFieldSection(): void
    {
        $item = (object) ['id' => 'SPEC-001'];
        $markdown = "**Origin**\n\noriginal\n\n**reason**\n\nAvoid silent\ndata loss.\n\nKeep input.\n\n**tests**\n\n- **unit:** Sample\\PassingTest::testPass\n\n**metadata**\n\n- **owner:** Parser team\n";
        (new FieldSections(new Presentation()))->read($item, MarkdownNodes::blocks($markdown), 'definition.md', 'SPEC-001');
        self::assertEquals((object) [
            'id' => 'SPEC-001',
            'origin' => 'original',
            'reason' => "Avoid silent\ndata loss.\n\nKeep input.",
            'tests' => [(object) ['runner' => 'unit', 'target' => 'Sample\\PassingTest::testPass']],
            'metadata' => (object) ['owner' => 'Parser team'],
        ], $item);
    }

    public function testReadReadsNothingFromNoBlocks(): void
    {
        $item = (object) ['id' => 'SPEC-001'];
        $presentation = new Presentation();
        (new FieldSections($presentation))->read($item, [], 'definition.md', 'SPEC-001');
        self::assertEquals((object) ['id' => 'SPEC-001'], $item);
        self::assertSame([], $presentation->links);
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerReasonSpellings')]
    public function testReadAcceptsTheSpellingsOfTheReasonField(string $heading): void
    {
        $item = new stdClass();
        (new FieldSections(new Presentation()))->read($item, MarkdownNodes::blocks("**$heading**\n\nThis library does not generate C code.\n"), 'definition.md', 'GENERATOR-001');
        self::assertEquals((object) ['reason' => 'This library does not generate C code.'], $item);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerReasonSpellings(): array
    {
        return [
            'reason' => ['reason'],
            'unsupport reason' => ['unsupport reason'],
            'unsupported reason' => ['unsupported reason'],
            'rationale' => ['Rationale'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRemembersLinksAndReferences(): void
    {
        $presentation = new Presentation();
        $markdown = "**origin**\n\noriginal\n\n**requirements**\n\n- [REQ-001](reference.yaml#req-001)\n\n**related**\n\n- [SPEC-002](#spec-002)\n";
        (new FieldSections($presentation))->read(new stdClass(), MarkdownNodes::blocks($markdown), 'definition.md', 'SPEC-001');
        self::assertSame(['SPEC-001' => ['origin' => [], 'requirements' => ['REQ-001' => 'reference.yaml#req-001'], 'related' => ['SPEC-002' => '#spec-002']]], $presentation->links);
        self::assertEquals([new Reference('REQ-001', 'reference.yaml#req-001', 'definition.md'), new Reference('SPEC-002', '#spec-002', 'definition.md')], $presentation->references);
        self::assertSame([], $presentation->badges);
    }

    /**
     * @throws CommonMarkException
     */
    public function testReadRemembersLabelBadges(): void
    {
        $presentation = new Presentation();
        $presentation->badges['SPEC-001']['kind'] = ['requirement' => ['url' => 'kind.svg', 'title' => null]];
        $item = new stdClass();
        (new FieldSections($presentation))->read($item, MarkdownNodes::blocks("**labels**\n\n![grammar](assets/grammar.svg) ![strictness](https://example.invalid/badge.svg)\n"), 'definition.md', 'SPEC-001');
        self::assertSame(['grammar', 'strictness'], $item->labels);
        self::assertSame(['SPEC-001' => [
            'kind' => ['requirement' => ['url' => 'kind.svg', 'title' => null]],
            'label' => ['grammar' => ['url' => 'assets/grammar.svg', 'title' => null], 'strictness' => ['url' => 'https://example.invalid/badge.svg', 'title' => null]],
        ]], $presentation->badges);
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedSections')]
    public function testReadRejectsMalformedSections(string $markdown, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new FieldSections(new Presentation()))->read(new stdClass(), MarkdownNodes::blocks($markdown), 'definition.md', 'SPEC-001');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerMalformedSections(): array
    {
        return [
            'extra paragraph' => ["Another paragraph.\n\n**origin**\n\noriginal\n", 'definition.md: SPEC-001 expects a bold field heading after its statement.'],
            'duplicate field' => ["**origin**\n\noriginal\n\n**origin**\n\noriginal\n", "definition.md: SPEC-001 has duplicate field 'origin'."],
            'duplicate reason spelling' => ["**rationale**\n\nWhy.\n\n**reason**\n\nWhy.\n", "definition.md: SPEC-001 has duplicate field 'reason'."],
            'unknown field' => ["**lables**\n\n- grammar\n", "definition.md: SPEC-001.lables: Unknown Markdown field 'lables'."],
            'empty last field' => ["**origin**\n\noriginal\n\n**reason**\n", "definition.md: SPEC-001.reason: Markdown field 'reason' needs a value."],
            'empty field before another' => ["**origin**\n\n**reason**\n\nWhy.\n", "definition.md: SPEC-001.origin: Markdown field 'origin' needs a value."],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testAssignReadsOneFieldIntoTheItem(): void
    {
        $item = new stdClass();
        $presentation = new Presentation();
        (new FieldSections($presentation))->assign($item, 'requirements', MarkdownNodes::blocks('- [REQ-001](#req-001)'), 'definition.md', 'SPEC-001');
        self::assertSame(['REQ-001'], $item->requirements);
        self::assertSame(['SPEC-001' => ['requirements' => ['REQ-001' => '#req-001']]], $presentation->links);
        self::assertEquals([new Reference('REQ-001', '#req-001', 'definition.md')], $presentation->references);
    }

    /**
     * @throws CommonMarkException
     */
    public function testAssignRejectsAFieldTheItemAlreadyHas(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("definition.md: SPEC-001 has duplicate field 'category'.");
        (new FieldSections(new Presentation()))->assign((object) ['category' => 'grammar'], 'category', MarkdownNodes::blocks('lexical'), 'definition.md', 'SPEC-001');
    }

    public function testAssignKeepsTheCauseOfAMalformedValue(): void
    {
        $error = null;
        try {
            (new FieldSections(new Presentation()))->assign(new stdClass(), 'origin', [], 'definition.md', 'SPEC-001');
        } catch (InvalidInputException $caught) {
            $error = $caught;
        }
        self::assertInstanceOf(InvalidInputException::class, $error);
        self::assertInstanceOf(InvalidInputException::class, $error->getPrevious());
        self::assertSame("Markdown field 'origin' needs a value.", $error->getPrevious()->getMessage());
    }
}
