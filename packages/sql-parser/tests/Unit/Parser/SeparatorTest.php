<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\RenderException;
use SqlParser\Parser\Separator;
use SqlParser\Parser\Spacing;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Separator::class)]
#[UsesClass(RenderException::class)]
#[UsesClass(Spacing::class)]
#[UsesClass(Token::class)]
#[Small]
final class SeparatorTest extends TestCase
{
    public function testReadingNamesTheTerminalsOfAText(): void
    {
        self::assertSame(['SELECT SELECT', 'INTEGER 1'], (new Separator(new SqliteParser()))->reading('SELECT 1'));
    }

    public function testReadingAnswersNullForTextNoTokenStartsWith(): void
    {
        self::assertNull((new Separator(new SqliteParser()))->reading("SELECT 'unterminated"));
    }

    public function testTerminalsNamesTheTerminalsOfTokens(): void
    {
        $tokens = [new Token(1, 'SELECT', 'SELECT', 0), new Token(2, 'INTEGER', '1', 7)];

        self::assertSame(['SELECT SELECT', 'INTEGER 1'], (new Separator(new SqliteParser()))->terminals($tokens));
    }

    public function testReadsComparesEveryTokenWhenTheLastIsSettled(): void
    {
        $separator = new Separator(new SqliteParser());
        $tokens = [new Token(1, 'SELECT', 'SELECT', 0), new Token(2, 'INTEGER', '1', 7)];

        self::assertTrue($separator->reads('SELECT 1', $tokens));
        self::assertFalse($separator->reads('SELECT 2', $tokens));
    }

    public function testReadsLeavesTheLastTokenOpenWhenItIsNotSettled(): void
    {
        $separator = new Separator(new SqliteParser());
        $tokens = [new Token(1, 'SELECT', 'SELECT', 0), new Token(2, 'INTEGER', '1', 7)];

        self::assertTrue($separator->reads('SELECT 2', $tokens, false));
        self::assertFalse($separator->reads('SELECT 1 1', $tokens, false));
    }

    public function testReadsRejectsTextNoTokenStartsWith(): void
    {
        $tokens = [new Token(1, 'SELECT', 'SELECT', 0)];

        self::assertFalse((new Separator(new SqliteParser()))->reads("SELECT 'x", $tokens, false));
    }

    public function testCandidatesPutsWhatTheTokensLookToNeedFirst(): void
    {
        $separator = new Separator(new SqliteParser());
        $apart = new Token(1, 'SELECT', 'SELECT', 0);
        $name = new Token(2, 'ID', 'a', 7);
        $dot = new Token(3, 'DOT', '.', 8);

        self::assertSame([' ', '', '/**/'], $separator->candidates($apart, $name));
        self::assertSame(['', ' ', '/**/'], $separator->candidates($name, $dot));
    }

    public function testOptionsSettlesTheFirstTokenWithNothingBeforeIt(): void
    {
        $first = new Token(1, 'SELECT', 'SELECT', 0);

        self::assertSame([['', true]], (new Separator(new SqliteParser()))->options(null, $first, false));
    }

    public function testOptionsNeverLeavesTheLastTokenOpen(): void
    {
        $select = new Token(1, 'SELECT', 'SELECT', 0);
        $one = new Token(2, 'INTEGER', '1', 7);

        self::assertSame([[' ', true], ['', true], ['/**/', true]], (new Separator(new SqliteParser()))->options($select, $one, true));
    }

    public function testOptionsAsksEveryCandidateToSettleBeforeLeavingATokenOpen(): void
    {
        $select = new Token(1, 'SELECT', 'SELECT', 0);
        $name = new Token(2, 'ID', 'a', 7);

        self::assertSame(
            [[' ', true], ['', true], ['/**/', true], [' ', false], ['', false], ['/**/', false]],
            (new Separator(new SqliteParser()))->options($select, $name, false),
        );
    }

    public function testRebuildWritesTokensSoTheTextReadsBackAsThem(): void
    {
        $parser = new SqliteParser();
        $tokens = array_values(array_filter($parser->parse('SELECT a FROM t')->tokens(), static fn (Token $token): bool => $token->text !== ''));
        $built = array_map(static fn (Token $token): Token => $token->detached(), $tokens);

        self::assertSame('SELECT a FROM t', (new Separator($parser))->rebuild($built));
    }

    public function testRebuildGivesUpOnTokensNothingCanSeparate(): void
    {
        $tokens = [
            new Token(1, 'ID', 'a', Token::DETACHED),
            new Token(2, 'NOT_A_TERMINAL', 'b', Token::DETACHED),
        ];

        $this->expectException(RenderException::class);

        (new Separator(new SqliteParser()))->rebuild($tokens);
    }
}
