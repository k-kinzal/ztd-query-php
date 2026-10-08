<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Pattern;

use MySqlMemory\Evaluation\Function\Pattern\Scanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scanner::class)]
#[Small]
final class ScannerTest extends TestCase
{
    public function testOfSplitsThePatternIntoCharacters(): void
    {
        self::assertSame(['a', 'é', '😀'], Scanner::of('aé😀')->characters);
    }

    public function testPeekLooksAheadWithoutReading(): void
    {
        $scanner = Scanner::of('ab');

        self::assertSame(['a', 'b', null, 0], [$scanner->peek(), $scanner->peek(1), $scanner->peek(2), $scanner->index]);
    }

    public function testNextCountsLinesAndCharactersAsTheServerDoes(): void
    {
        $scanner = Scanner::of("a\r\nb");
        $read = [$scanner->next(), $scanner->next(), $scanner->next(), $scanner->next(), $scanner->next()];

        self::assertSame([['a', "\r", "\n", 'b', null], 2, 2], [$read, $scanner->line, $scanner->column]);
    }

    public function testDoneTellsWhetherEveryCharacterWasRead(): void
    {
        $scanner = Scanner::of('a');
        $before = $scanner->done();
        $scanner->next();

        self::assertSame([false, true], [$before, $scanner->done()]);
    }

    public function testSyntaxNamesTheLineAndCharacterOfTheLastCharacterRead(): void
    {
        $scanner = Scanner::of("a\nb*");
        $scanner->next();
        $scanner->next();
        $scanner->next();
        $scanner->next();
        $error = $scanner->syntax();

        self::assertSame([3688, 'Syntax error in regular expression on line 2, character 2.'], [$error->getCode(), $error->getMessage()]);
    }
}
