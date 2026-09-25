<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Profile;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Parser\MarkdownParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\Markdown\Profile\BlockKind;
use Requirements\Config\Markdown\Profile\Occurrences;
use Requirements\Config\Markdown\Profile\SectionBlocks;
use Requirements\Config\Markdown\Profile\TextConstraint;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

#[CoversClass(SectionBlocks::class)]
#[UsesClass(BlockKind::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Nodes::class)]
#[UsesClass(Occurrences::class)]
#[UsesClass(TextConstraint::class)]
#[Small]
final class SectionBlocksTest extends TestCase
{
    /**
     * @param array<string, mixed> $schema
     * @throws CommonMarkException
     */
    #[DataProvider('providerAccepted')]
    public function testCheckAcceptsMatchingBlocks(string $markdown, array $schema): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $nodes = array_values([...(new MarkdownParser($environment))->parse($markdown)->children()]);
        SectionBlocks::check($nodes, $schema, 'doc.md');
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function providerAccepted(): array
    {
        return [
            'bundled section' => ["The reader shall emit a tree.\n\n> Quoted.\n\n- one\n", ['blocks' => [['type' => ['paragraph', 'bullet-list', 'quote'], 'minContains' => 1]], 'additionalBlocks' => false]],
            'type as a string' => ["> Quoted.\n", ['blocks' => [['type' => 'quote']]]],
            'entries in order' => ["A.\n\n- b\n", ['blocks' => [['type' => 'paragraph', 'maxContains' => 1], ['type' => 'bullet-list', 'minContains' => 0]], 'additionalBlocks' => false]],
            'entry repeats' => ["A.\n\nB.\n\nC.\n", ['blocks' => [['type' => 'paragraph', 'minContains' => 3]], 'additionalBlocks' => false]],
            'first matching entry only' => ["A.\n", ['blocks' => [['type' => 'paragraph'], ['type' => 'paragraph', 'minContains' => 0, 'maxContains' => 0]], 'additionalBlocks' => false]],
            'text constraint selects entry' => ["A.\n\nB.\n", ['blocks' => [['type' => 'paragraph', 'text' => ['pattern' => '^A'], 'maxContains' => 1], ['type' => 'paragraph', 'maxContains' => 1]], 'additionalBlocks' => false]],
            'text constraint sees nothing in a list' => ["- a\n", ['blocks' => [['type' => 'bullet-list', 'text' => ['const' => '']]], 'additionalBlocks' => false]],
            'text constraint reads paragraph text' => ["A *b*.\n", ['blocks' => [['type' => 'paragraph', 'text' => ['const' => 'A b.']]], 'additionalBlocks' => false]],
            'additional blocks allowed by default' => ["A.\n\n```\ncode\n```\n", ['blocks' => [['type' => 'paragraph']]]],
            'additional blocks allowed explicitly' => ["```\ncode\n```\n\nA.\n", ['blocks' => [['type' => 'paragraph']], 'additionalBlocks' => true]],
            'no blocks and no entries' => ['', ['blocks' => []]],
            'no blocks with optional entry' => ['', ['blocks' => [['type' => 'paragraph', 'minContains' => 0]]]],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerUnexpected')]
    public function testCheckRejectsUnexpectedBlock(string $markdown): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $nodes = array_values([...(new MarkdownParser($environment))->parse($markdown)->children()]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('doc.md: unexpected document-schema block.');
        SectionBlocks::check($nodes, ['blocks' => [['type' => 'paragraph', 'maxContains' => 1], ['type' => 'bullet-list', 'minContains' => 0, 'text' => ['pattern' => 'a']]], 'additionalBlocks' => false], 'doc.md');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerUnexpected(): array
    {
        return [
            'kind matching no entry' => ["A.\n\n```\ncode\n```\n"],
            'earlier entry after a later one' => ["A.\n\n- b\n\nC.\n"],
            'text constraint on a list' => ["A.\n\n- a\n"],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerOccurrences')]
    public function testCheckRejectsEntryOccurrences(string $markdown, string $message): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $nodes = array_values([...(new MarkdownParser($environment))->parse($markdown)->children()]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        SectionBlocks::check($nodes, ['blocks' => [['type' => 'paragraph', 'text' => ['pattern' => '^A'], 'maxContains' => 1], ['type' => ['paragraph', 'quote'], 'maxContains' => 2]]], 'doc.md');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerOccurrences(): array
    {
        return [
            'missing section' => ['', 'doc.md: section block 1: document-schema occurrence constraint failed.'],
            'first entry skipped' => ["B.\n\nA.\n", 'doc.md: section block 1: document-schema occurrence constraint failed.'],
            'first entry repeated' => ["A.\n\nA2.\n\nB.\n", 'doc.md: section block 1: document-schema occurrence constraint failed.'],
            'second entry missing' => ["A.\n", 'doc.md: section block 2: document-schema occurrence constraint failed.'],
            'second entry too often' => ["A.\n\nB.\n\n> C.\n\nD.\n", 'doc.md: section block 2: document-schema occurrence constraint failed.'],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @throws CommonMarkException
     */
    #[DataProvider('providerMalformedSchema')]
    public function testCheckRejectsMalformedSchema(array $schema, string $message): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $nodes = array_values([...(new MarkdownParser($environment))->parse("A.\n")->children()]);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        SectionBlocks::check($nodes, $schema, 'doc.md');
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function providerMalformedSchema(): array
    {
        return [
            'blocks not a list' => [['blocks' => ['first' => ['type' => 'paragraph']]], 'blocks must be a list.'],
            'entry not a mapping' => [['blocks' => ['paragraph']], 'block must be a mapping.'],
            'unknown entry key' => [['blocks' => [['type' => 'paragraph', 'header' => []]]], "block: unknown field 'header'."],
            'type neither string nor list' => [['blocks' => [['type' => 1]]], 'block.type must be a list.'],
            'text not a mapping' => [['blocks' => [['type' => 'paragraph', 'text' => 'A.']]], 'block.text must be a mapping.'],
        ];
    }
}
