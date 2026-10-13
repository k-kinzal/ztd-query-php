<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Construction\SourceRanges;
use WeakReference;

#[CoversClass(SourceRanges::class)]
#[Small]
final class SourceRangesTest extends TestCase
{
    /**
     * @return iterable<string, array{Node}>
     */
    public static function providerNestedSources(): iterable
    {
        $node = new Node('leaf', 0, [new Token(1, 'NUM', '12', 5)]);
        yield 'leaf' => [$node];
        for ($depth = 0; $depth < 2000; $depth++) {
            $node = new Node('wrapper', 0, [new Node('empty', 0, []), $node]);
        }
        yield 'deep wrappers' => [$node];
    }

    #[DataProvider('providerNestedSources')]
    public function testSpanPreservesTheRangeThroughNestedSources(Node $node): void
    {
        $ranges = new SourceRanges();

        self::assertSame([5, 7], $ranges->span($node));
        self::assertSame([5, 7], $ranges->span($node, true));
    }

    public function testSpanDistinguishesSyntheticTokensFromVisibleTokens(): void
    {
        $source = new Node('root', 0, [new Token(0, 'START', '', 0), new Node('nested', 0, [new Token(1, 'NUM', '1', 5)]), new Token(0, 'END', '', 10)]);
        $ranges = new SourceRanges();

        self::assertSame([5, 6], $ranges->span($source));
        self::assertSame([0, 10], $ranges->span($source, true));
        self::assertNull($ranges->span(new Node('empty', 0, [])));
        self::assertNull($ranges->span(new Node('synthetic', 0, [new Token(0, 'END', '', 10)])));
    }

    public function testSpanDoesNotKeepItsParseTreeAlive(): void
    {
        $ranges = new SourceRanges();
        $source = new Node('root', 0, [new Token(1, 'NUM', '1', 5)]);
        $weak = WeakReference::create($source);
        self::assertSame([5, 6], $ranges->span($source));
        unset($source);

        self::assertNull($weak->get());
    }
}
