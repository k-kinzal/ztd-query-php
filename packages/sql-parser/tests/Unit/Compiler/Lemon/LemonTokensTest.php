<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\Lemon\LemonToken;
use SqlParser\Compiler\Lemon\LemonTokenKind;
use SqlParser\Compiler\Lemon\LemonTokens;

#[CoversClass(LemonTokens::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(LemonToken::class)]
#[Small]
final class LemonTokensTest extends TestCase
{
    public function testPeek(): void
    {
        $first = new LemonToken(LemonTokenKind::Identifier, 'a', 1);
        $tokens = new LemonTokens([$first]);

        self::assertSame($first, $tokens->peek());
        self::assertNull($tokens->peek(1));
    }

    public function testNext(): void
    {
        $first = new LemonToken(LemonTokenKind::Identifier, 'a', 1);
        $tokens = new LemonTokens([$first]);

        self::assertSame($first, $tokens->next());
        self::assertNull($tokens->next());
    }

    public function testAtEnd(): void
    {
        $tokens = new LemonTokens([new LemonToken(LemonTokenKind::Dot, '.', 1)]);

        self::assertFalse($tokens->atEnd());
        $tokens->next();
        self::assertTrue($tokens->atEnd());
    }

    public function testTake(): void
    {
        $tokens = new LemonTokens([new LemonToken(LemonTokenKind::Identifier, 'a', 1)]);

        self::assertSame('a', $tokens->take(LemonTokenKind::Identifier, 'a name')->text);
    }

    public function testTakeRejectsAnotherKind(): void
    {
        $tokens = new LemonTokens([new LemonToken(LemonTokenKind::Dot, '.', 3)]);

        $this->expectException(GrammarSourceException::class);
        $this->expectExceptionMessage("Expected a name but found '.' on line 3");

        $tokens->take(LemonTokenKind::Identifier, 'a name');
    }

    public function testTakeRejectsTheEnd(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonTokens([]))->take(LemonTokenKind::Identifier, 'a name');
    }

    public function testNamesUntilDot(): void
    {
        $tokens = new LemonTokens([new LemonToken(LemonTokenKind::Identifier, 'A', 1), new LemonToken(LemonTokenKind::Pipe, '|', 1), new LemonToken(LemonTokenKind::Identifier, 'B', 1), new LemonToken(LemonTokenKind::Dot, '.', 1), new LemonToken(LemonTokenKind::Identifier, 'C', 2)]);

        self::assertSame(['A', 'B'], $tokens->namesUntilDot());
        self::assertSame('C', $tokens->peek()?->text);
    }

    public function testNamesUntilDotRejectsAnUnterminatedList(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonTokens([new LemonToken(LemonTokenKind::Identifier, 'A', 1)]))->namesUntilDot();
    }
}
