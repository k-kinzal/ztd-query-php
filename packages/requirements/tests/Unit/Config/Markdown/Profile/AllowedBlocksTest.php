<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Profile;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Profile\AllowedBlocks;
use Requirements\Config\Markdown\Profile\BlockKind;
use Requirements\Config\Markdown\Quotation;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;

#[CoversClass(AllowedBlocks::class)]
#[UsesClass(BlockKind::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Quotation::class)]
#[Small]
final class AllowedBlocksTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerAllowed')]
    public function testCheckAcceptsAllowedBlocks(string $markdown): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $node = (new MarkdownParser($environment))->parse($markdown)->firstChild();
        self::assertInstanceOf(Node::class, $node);
        AllowedBlocks::check($node, ['type' => ['paragraph', 'bullet-list', 'quote']], 'doc.md');
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerAllowed(): array
    {
        return [
            'paragraph' => ["The reader shall emit a tree.\n"],
            'bullet list' => ["- one\n- two\n"],
            'nested bullet list' => ["- one\n  - two\n"],
            'quote' => ["> Quoted.\n"],
            'quote starting with selector comment' => ["> <!-- selector: #a -->\n> Quoted.\n"],
            'quote starting with bold selector comment' => ["> <!-- **selector:** #a -->\n> Quoted.\n"],
            'nested quote starting with selector comment' => ["> > <!-- selector: #a -->\n> > Quoted.\n"],
            'quote in a list item' => ["- > <!-- selector: #a -->\n  > Quoted.\n"],
            'list in a quote' => ["> - one\n"],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerForbidden')]
    public function testCheckRejectsForbiddenBlocks(string $markdown, string $kind): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $node = (new MarkdownParser($environment))->parse($markdown)->firstChild();
        self::assertInstanceOf(Node::class, $node);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("doc.md: document-schema forbids $kind blocks; use quotations, paragraphs and bullet lists.");
        AllowedBlocks::check($node, ['type' => ['paragraph', 'bullet-list', 'quote']], 'doc.md');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerForbidden(): array
    {
        return [
            'ordered list' => ["1. one\n", 'ordered-list'],
            'code fence' => ["```\ncode\n```\n", 'unsupported'],
            'heading' => ["# Title\n", 'unsupported'],
            'comment outside a quote' => ["<!-- selector: #a -->\n", 'unsupported'],
            'comment after the start of a quote' => ["> Quoted.\n>\n> <!-- selector: #a -->\n", 'unsupported'],
            'code fence in a quote' => ["> ```\n> code\n> ```\n", 'unsupported'],
            'code fence in a list item' => ["- one\n\n  ```\n  code\n  ```\n", 'unsupported'],
            'ordered list in a list item' => ["- one\n  1. two\n", 'ordered-list'],
            'code fence in a nested quote' => ["> > ```\n> > code\n> > ```\n", 'unsupported'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testCheckRejectsKindMissingFromSchema(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $node = (new MarkdownParser($environment))->parse("> Quoted.\n")->firstChild();
        self::assertInstanceOf(Node::class, $node);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('doc.md: document-schema forbids quote blocks; use quotations, paragraphs and bullet lists.');
        AllowedBlocks::check($node, ['type' => ['paragraph']], 'doc.md');
    }

    /**
     * @throws CommonMarkException
     */
    public function testCheckRejectsNestedKindMissingFromSchema(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $node = (new MarkdownParser($environment))->parse("> Quoted.\n")->firstChild();
        self::assertInstanceOf(Node::class, $node);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('doc.md: document-schema forbids paragraph blocks; use quotations, paragraphs and bullet lists.');
        AllowedBlocks::check($node, ['type' => ['quote']], 'doc.md');
    }

    /**
     * @throws CommonMarkException
     */
    public function testCheckRejectsCommentThatIsNotSelector(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $node = (new MarkdownParser($environment))->parse("> <!-- note -->\n> Quoted.\n")->firstChild();
        self::assertInstanceOf(Node::class, $node);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Only a selector comment is allowed at the start of an evidence quotation.');
        AllowedBlocks::check($node, ['type' => ['paragraph', 'bullet-list', 'quote']], 'doc.md');
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
        $node = (new MarkdownParser($environment))->parse("Text.\n")->firstChild();
        self::assertInstanceOf(Node::class, $node);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        AllowedBlocks::check($node, $schema, 'doc.md');
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function providerMalformedSchema(): array
    {
        return [
            'unknown key' => [['type' => ['paragraph'], 'items' => []], "allBlocks: unknown field 'items'."],
            'type not a list' => [['type' => 'paragraph'], 'allBlocks.type must be a list.'],
        ];
    }
}
