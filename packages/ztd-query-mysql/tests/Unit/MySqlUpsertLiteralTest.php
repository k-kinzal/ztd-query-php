<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlUpsertLiteral;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

#[CoversClass(MySqlUpsertLiteral::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class MySqlUpsertLiteralTest extends TestCase
{
    public function testNumberOfReadsAWholeNumberAsAnInteger(): void
    {
        self::assertSame(42, (new MySqlUpsertLiteral())->numberOf('42'));
    }

    public function testNumberOfReadsANumberWithAPointAsAFloat(): void
    {
        self::assertSame(1.5, (new MySqlUpsertLiteral())->numberOf('1.5'));
    }

    public function testNumberOfReadsAnExponentAsAFloat(): void
    {
        self::assertSame(150.0, (new MySqlUpsertLiteral())->numberOf('1.5e2'));
    }

    public function testNumberOfReadsHexAsTheIntegerItSpells(): void
    {
        self::assertSame(255, (new MySqlUpsertLiteral())->numberOf('0xff'));
    }

    public function testNumberOfRefusesTextThatIsNoNumberAtAll(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        (new MySqlUpsertLiteral())->numberOf('12abc');
    }

    public function testTextOfAnswersWhatTheStringStandsFor(): void
    {
        self::assertSame('abc', (new MySqlUpsertLiteral())->textOf("'abc'"));
    }

    public function testTextOfReadsADoubledQuoteAsOneQuote(): void
    {
        self::assertSame("it's", (new MySqlUpsertLiteral())->textOf("'it''s'"));
    }

    public function testTextOfReadsAnEscapedQuoteAsOneQuote(): void
    {
        self::assertSame("it's", (new MySqlUpsertLiteral())->textOf("'it\\'s'"));
    }

    public function testNameOfAnswersABareWordAsItWasWritten(): void
    {
        self::assertSame('qty', (new MySqlUpsertLiteral())->nameOf(
            new SqlToken(SqlTokenKind::Word, 'qty', 0, 0, 0),
        ));
    }

    public function testNameOfTakesTheQuotingOffAQuotedName(): void
    {
        self::assertSame('order', (new MySqlUpsertLiteral())->nameOf(
            new SqlToken(SqlTokenKind::QuotedIdentifier, '`order`', 0, 0, 0),
        ));
    }

    public function testNameOfReadsADoubledQuoteInsideANameAsOneQuote(): void
    {
        self::assertSame('a`b', (new MySqlUpsertLiteral())->nameOf(
            new SqlToken(SqlTokenKind::QuotedIdentifier, '`a``b`', 0, 0, 0),
        ));
    }

    public function testIsNameReportsABareWord(): void
    {
        self::assertTrue((new MySqlUpsertLiteral())->isName(new SqlToken(SqlTokenKind::Word, 'qty', 0, 0, 0)));
    }

    public function testIsNameIsFalseForSomethingThatIsNotAName(): void
    {
        self::assertFalse((new MySqlUpsertLiteral())->isName(new SqlToken(SqlTokenKind::Number, '1', 0, 0, 0)));
    }

    public function testIsSymbolReportsOneOfTheSymbolsItWasGiven(): void
    {
        self::assertTrue((new MySqlUpsertLiteral())->isSymbol(
            new SqlToken(SqlTokenKind::Symbol, '+', 0, 0, 0),
            ['+', '-'],
        ));
    }

    public function testIsSymbolIsFalseForASymbolItWasNotGiven(): void
    {
        self::assertFalse((new MySqlUpsertLiteral())->isSymbol(
            new SqlToken(SqlTokenKind::Symbol, '*', 0, 0, 0),
            ['+', '-'],
        ));
    }
}
