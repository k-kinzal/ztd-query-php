<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Parse\PgSqlUpsertLiteral;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(PgSqlUpsertLiteral::class)]
final class PgSqlUpsertLiteralTest extends TestCase
{
    public function testNumberOfReadsAWholeNumberAsAnInteger(): void
    {
        self::assertSame(42, (new PgSqlUpsertLiteral())->numberOf('42'));
    }

    public function testNumberOfReadsANumberWithAPointAsAFloat(): void
    {
        self::assertSame(1.5, (new PgSqlUpsertLiteral())->numberOf('1.5'));
    }

    public function testNumberOfReadsAnExponentAsAFloat(): void
    {
        self::assertSame(150.0, (new PgSqlUpsertLiteral())->numberOf('1.5e2'));
    }

    public function testNumberOfReadsUnderscoresAsGroupingBetweenDigits(): void
    {
        self::assertSame(1000, (new PgSqlUpsertLiteral())->numberOf('1_000'));
    }

    public function testNumberOfRefusesTextThatIsNoNumberAtAll(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new PgSqlUpsertLiteral())->numberOf('12abc');
    }

    public function testTextOfAnswersWhatTheStringStandsFor(): void
    {
        self::assertSame('abc', (new PgSqlUpsertLiteral())->textOf("'abc'"));
    }

    public function testTextOfReadsADoubledQuoteAsOneQuote(): void
    {
        self::assertSame("it's", (new PgSqlUpsertLiteral())->textOf("'it''s'"));
    }

    public function testTextOfLeavesABackslashAsTheByteItIs(): void
    {
        self::assertSame('a\\b', (new PgSqlUpsertLiteral())->textOf("'a\\b'"));
    }

    public function testNameOfAnswersABareWordAsItWasWritten(): void
    {
        self::assertSame('qty', (new PgSqlUpsertLiteral())->nameOf(
            new SqlToken(SqlTokenKind::Word, 'qty', 0, 0, 0),
        ));
    }

    public function testNameOfTakesTheQuotingOffAQuotedName(): void
    {
        self::assertSame('order', (new PgSqlUpsertLiteral())->nameOf(
            new SqlToken(SqlTokenKind::QuotedIdentifier, '"order"', 0, 0, 0),
        ));
    }

    public function testNameOfReadsADoubledQuoteInsideANameAsOneQuote(): void
    {
        self::assertSame('a"b', (new PgSqlUpsertLiteral())->nameOf(
            new SqlToken(SqlTokenKind::QuotedIdentifier, '"a""b"', 0, 0, 0),
        ));
    }

    public function testIsNameIsFalseForANameQuotedTheWayAnotherDialectQuotesOne(): void
    {
        self::assertFalse((new PgSqlUpsertLiteral())->isName(
            new SqlToken(SqlTokenKind::QuotedIdentifier, '`order`', 0, 0, 0),
        ));
    }

    public function testIsNameReportsABareWord(): void
    {
        self::assertTrue((new PgSqlUpsertLiteral())->isName(new SqlToken(SqlTokenKind::Word, 'qty', 0, 0, 0)));
    }

    public function testIsNameIsFalseForSomethingThatIsNotAName(): void
    {
        self::assertFalse((new PgSqlUpsertLiteral())->isName(new SqlToken(SqlTokenKind::Number, '1', 0, 0, 0)));
    }

    public function testIsSymbolReportsOneOfTheSymbolsItWasGiven(): void
    {
        self::assertTrue((new PgSqlUpsertLiteral())->isSymbol(
            new SqlToken(SqlTokenKind::Symbol, '+', 0, 0, 0),
            ['+', '-'],
        ));
    }

    public function testIsSymbolIsFalseForASymbolItWasNotGiven(): void
    {
        self::assertFalse((new PgSqlUpsertLiteral())->isSymbol(
            new SqlToken(SqlTokenKind::Symbol, '*', 0, 0, 0),
            ['+', '-'],
        ));
    }
}
