<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Session\Parse\DiagnosticLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;

#[CoversClass(DiagnosticLine::class)]
#[Medium]
final class DiagnosticLineTest extends TestCase
{
    public function testAtDistinguishesQuotedIdentifierNewlinesByRelease(): void
    {
        $sql = "\nCREATE PROCEDURE p(`x\n` INT)\n nonsense";
        $lines = new DiagnosticLine();

        self::assertSame(2, $lines->at($sql, strlen($sql), true));
        self::assertSame(3, $lines->at($sql, strlen($sql), false));
        self::assertSame(1, $lines->at($sql, 15, true));
    }

    public function testAtExcludesBothQuotedNamesAndTextOnLegacyReleases(): void
    {
        $sql = "CREATE PROCEDURE p(\"x\n\" INT) nonsense";
        $parser = new MySqlParser('mysql-5.7.44', SqlMode::fromString('ANSI_QUOTES'));

        self::assertSame(1, (new DiagnosticLine())->at($sql, strlen($sql), true, $parser));
        self::assertSame(1, (new DiagnosticLine())->at($sql, strlen($sql), true));
    }

    public function testAtKeepsCompletedQuotedIdentifiersBeforeALaterLexicalFailure(): void
    {
        $sql = "SELECT `a\nb` nonsense ]";

        $offset = strpos($sql, 'nonsense');
        self::assertNotFalse($offset);
        self::assertSame(1, (new DiagnosticLine())->at($sql, $offset, true));
        self::assertSame(2, (new DiagnosticLine())->at($sql, $offset, false));
    }

    public function testAtKeepsTheOffsetLineWhenTokenizationFails(): void
    {
        $sql = "SELECT\n'unclosed";

        self::assertSame(2, (new DiagnosticLine())->at($sql, strlen($sql), true));
    }
}
