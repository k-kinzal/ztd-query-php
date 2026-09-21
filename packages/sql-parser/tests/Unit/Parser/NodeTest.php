<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

#[CoversClass(Node::class)]
#[UsesClass(Token::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class NodeTest extends TestCase
{
    public function testIsEmpty(): void
    {
        self::assertTrue((new Node('opt', 0, []))->isEmpty());
        self::assertFalse((new Node('expr', 0, [new Token(1, 'NUM', '1', 0)]))->isEmpty());
    }

    public function testTokens(): void
    {
        $select = new Token(3, 'SELECT', 'SELECT', 0);
        $one = new Token(7, 'NUM', '1', 7);
        $tree = new Node('statement', 0, [$select, new Node('expr', 2, [$one]), new Node('opt', 0, [])]);

        self::assertSame([$select, $one], $tree->tokens());
    }

    public function testFind(): void
    {
        $inner = new Node('expr', 1, [new Token(7, 'NUM', '1', 7)]);
        $outer = new Node('expr', 0, [$inner]);
        $tree = new Node('statement', 0, [$outer]);

        self::assertSame([$outer, $inner], $tree->find('expr'));
        self::assertSame([$tree], $tree->find('statement'));
        self::assertSame([], $tree->find('missing'));
    }

    public function testSpan(): void
    {
        $tree = new Node('statement', 0, [new Token(3, 'SELECT', 'SELECT', 0), new Node('expr', 2, [new Token(7, 'NUM', '1', 7)])]);

        self::assertSame([0, 8], $tree->span());
        self::assertNull((new Node('opt', 0, []))->span());
    }

    public function testText(): void
    {
        $sql = 'SELECT 1 ';
        $tree = new Node('statement', 0, [new Token(3, 'SELECT', 'SELECT', 0), new Node('expr', 2, [new Token(7, 'NUM', '1', 7)])]);

        self::assertSame('SELECT 1', $tree->text($sql));
        self::assertSame('', (new Node('opt', 0, []))->text($sql));
    }

    public function testToStringWritesBackTheTextTheTreeWasParsedFrom(): void
    {
        $sql = "SELECT /* one */ 1 -- done\n";
        $tree = new Node('statement', 0, [
            new Token(3, 'SELECT', 'SELECT', 0),
            new Node('expr', 2, [new Token(7, 'NUM', '1', 17, ' /* one */ ')]),
            new Node('opt', 0, []),
        ], " -- done\n");

        self::assertSame($sql, $tree->toString());
        self::assertSame('', (new Node('opt', 0, []))->toString());
    }
}
