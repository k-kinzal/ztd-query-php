<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Syntax\NodeReader as Subject;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

#[CoversClass(Subject::class)]
final class NodeReaderTest extends TestCase
{
    public function testChildReturnsTheFirstDirectChildWithTheName(): void
    {
        $first = new Node('item', 0, [new Token(1, 'NUM', '1', 0)]);
        $second = new Node('item', 0, [new Token(1, 'NUM', '2', 2)]);
        $nested = new Node('list', 0, [new Node('item', 0, [new Token(1, 'NUM', '3', 4)])]);
        $node = new Node('root', 0, [$nested, $first, $second]);

        self::assertSame($first, (new Subject())->child($node, 'item'));
        self::assertSame($nested, (new Subject())->child($node, 'list'));
        self::assertNull((new Subject())->child($node, 'missing'));
    }

    public function testChildIgnoresTokensWithTheSameName(): void
    {
        $node = new Node('root', 0, [new Token(1, 'item', 'x', 0)]);

        self::assertNull((new Subject())->child($node, 'item'));
    }

    public function testTokenReturnsOnlyDirectChildTokens(): void
    {
        $direct = new Token(2, 'KEY', 'KEY', 8);
        $node = new Node('root', 0, [new Node('inner', 0, [new Token(2, 'KEY', 'KEY', 0)]), new Token(1, 'PRIMARY', 'PRIMARY', 0), $direct]);

        self::assertSame($direct, (new Subject())->token($node, 'KEY'));
        self::assertNull((new Subject())->token($node, 'NULL'));
    }

    public function testFirstTokenDescendsIntoNestedNodes(): void
    {
        $token = new Token(1, 'IDENT', 'id', 0);
        $node = new Node('root', 0, [new Node('empty', 0, []), new Node('inner', 0, [$token]), new Token(1, 'IDENT', 'later', 3)]);

        self::assertSame($token, (new Subject())->firstToken($node));
        self::assertNull((new Subject())->firstToken(new Node('root', 0, [])));
    }

    public function testContainsTokenSearchesTheWholeSubtree(): void
    {
        $node = new Node('root', 0, [new Node('inner', 0, [new Node('deep', 0, [new Token(1, 'UNSIGNED_SYM', 'UNSIGNED', 0)])])]);

        self::assertTrue((new Subject())->containsToken($node, 'UNSIGNED_SYM'));
        self::assertFalse((new Subject())->containsToken($node, 'ZEROFILL_SYM'));
    }

    public function testWordsOutsideParenthesesSkipsNestedGroupsAndEmptyTokens(): void
    {
        $node = new Node('type', 0, [
            new Token(1, 'TIMESTAMP', 'TIMESTAMP', 0),
            new Token(2, '(', '(', 9),
            new Node('precision', 0, [new Token(3, 'NUM', '3', 10), new Token(2, '(', '(', 11), new Token(3, 'NUM', '4', 12), new Token(4, ')', ')', 13)]),
            new Token(4, ')', ')', 14),
            new Token(5, 'WITH', 'WITH', 16),
            new Token(6, 'END', '', 20),
            new Token(7, 'TIME', 'TIME', 21),
        ]);

        self::assertSame(['TIMESTAMP', 'WITH', 'TIME'], (new Subject())->wordsOutsideParentheses($node));
        self::assertSame([], (new Subject())->wordsOutsideParentheses(new Node('empty', 0, [])));
    }
}
