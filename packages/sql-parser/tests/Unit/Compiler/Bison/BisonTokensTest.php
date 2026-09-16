<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Bison\BisonToken;
use SqlParser\Compiler\Bison\BisonTokenKind;
use SqlParser\Compiler\Bison\BisonTokens;
use SqlParser\Compiler\GrammarSourceException;

#[CoversClass(BisonTokens::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(GrammarSourceException::class)]
#[Small]
final class BisonTokensTest extends TestCase
{
    public function testPeek(): void
    {
        $first = new BisonToken(BisonTokenKind::Identifier, 'a', 1);
        $second = new BisonToken(BisonTokenKind::Colon, ':', 1);
        $tokens = new BisonTokens([$first, $second]);

        self::assertSame($first, $tokens->peek());
        self::assertSame($second, $tokens->peek(1));
        self::assertNull($tokens->peek(2));
    }

    public function testNext(): void
    {
        $first = new BisonToken(BisonTokenKind::Identifier, 'a', 1);
        $tokens = new BisonTokens([$first]);

        self::assertSame($first, $tokens->next());
        self::assertNull($tokens->next());
    }

    public function testAtEnd(): void
    {
        $tokens = new BisonTokens([new BisonToken(BisonTokenKind::Identifier, 'a', 1)]);

        self::assertFalse($tokens->atEnd());
        $tokens->next();
        self::assertTrue($tokens->atEnd());
    }

    public function testTake(): void
    {
        $tokens = new BisonTokens([new BisonToken(BisonTokenKind::Identifier, 'a', 1)]);

        self::assertSame('a', $tokens->take(BisonTokenKind::Identifier, 'a name')->text);
    }

    public function testTakeRejectsAnotherKind(): void
    {
        $tokens = new BisonTokens([new BisonToken(BisonTokenKind::Colon, ':', 7)]);

        $this->expectException(GrammarSourceException::class);
        $this->expectExceptionMessage("Expected a name but found ':' on line 7");

        $tokens->take(BisonTokenKind::Identifier, 'a name');
    }

    public function testTakeRejectsTheEnd(): void
    {
        $tokens = new BisonTokens([new BisonToken(BisonTokenKind::Colon, ':', 7)]);
        $tokens->next();

        $this->expectException(GrammarSourceException::class);
        $this->expectExceptionMessage('Expected a name but found the end of the grammar on line 7');

        $tokens->take(BisonTokenKind::Identifier, 'a name');
    }
}
