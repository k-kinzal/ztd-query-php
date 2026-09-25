<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown\Profile;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\Profile\BlockKind;

#[CoversClass(BlockKind::class)]
#[Small]
final class BlockKindTest extends TestCase
{
    /**
     * @throws CommonMarkException
     */
    #[DataProvider('providerBlocks')]
    public function testOfNamesBlockKind(string $markdown, string $kind): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $node = (new MarkdownParser($environment))->parse($markdown)->firstChild();
        self::assertInstanceOf(Node::class, $node);
        self::assertSame($kind, BlockKind::of($node));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerBlocks(): array
    {
        return [
            'paragraph' => ["Some text.\n", 'paragraph'],
            'quote' => ["> Quoted.\n", 'quote'],
            'dash bullet list' => ["- one\n- two\n", 'bullet-list'],
            'star bullet list' => ["* one\n", 'bullet-list'],
            'plus bullet list' => ["+ one\n", 'bullet-list'],
            'dot ordered list' => ["1. one\n", 'ordered-list'],
            'parenthesis ordered list' => ["3) three\n", 'ordered-list'],
            'heading' => ["# Title\n", 'unsupported'],
            'code fence' => ["```\ncode\n```\n", 'unsupported'],
            'indented code' => ["    code\n", 'unsupported'],
            'thematic break' => ["***\n", 'unsupported'],
            'html comment' => ["<!-- note -->\n", 'unsupported'],
        ];
    }

    /**
     * @throws CommonMarkException
     */
    public function testOfNamesListItemUnsupported(): void
    {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $list = (new MarkdownParser($environment))->parse("- one\n")->firstChild();
        self::assertInstanceOf(ListBlock::class, $list);
        $item = $list->firstChild();
        self::assertInstanceOf(Node::class, $item);
        self::assertSame('unsupported', BlockKind::of($item));
    }
}
