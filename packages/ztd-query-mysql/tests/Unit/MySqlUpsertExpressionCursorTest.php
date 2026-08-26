<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\MySqlUpsertExpressionCursor;
use ZtdQuery\Platform\MySql\MySqlUpsertLiteral;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenScanner;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlUpsertExpressionCursor::class)]
#[UsesClass(MySqlUpsertLiteral::class)]
#[UsesClass(MySqlLexerProfile::class)]
#[UsesClass(SqlToken::class)]
#[UsesClass(SqlTokenStream::class)]
#[UsesClass(SqlTokenScanner::class)]
#[UsesClass(UnsupportedSqlException::class)]
final class MySqlUpsertExpressionCursorTest extends TestCase
{
    public function testOverStartsAtTheFirstThingTheExpressionSays(): void
    {
        self::assertSame('qty', MySqlUpsertExpressionCursor::over('qty + 1', 'items')->token()?->text);
    }

    public function testTokenIsNothingPastTheEndOfTheExpression(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('1', 'items');
        $cursor->advance();

        self::assertNull($cursor->token());
    }

    public function testTokenAtLooksFurtherAlongWithoutMovingTheCursor(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('qty + 1', 'items');

        self::assertSame(['+', 'qty'], [$cursor->tokenAt(1)?->text, $cursor->token()?->text]);
    }

    public function testAdvanceMovesPastAsManyTokensAsItIsTold(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('qty + 1', 'items');
        $cursor->advance(2);

        self::assertSame('1', $cursor->token()?->text);
    }

    public function testAtEndIsFalseWhileAnythingIsLeft(): void
    {
        self::assertFalse(MySqlUpsertExpressionCursor::over('1', 'items')->atEnd());
    }

    public function testAtEndReportsThatTheWholeExpressionHasBeenRead(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('1', 'items');
        $cursor->advance();

        self::assertTrue($cursor->atEnd());
    }

    public function testIsKeywordReportsTheKeywordTheCursorIsOn(): void
    {
        self::assertTrue(MySqlUpsertExpressionCursor::over('NOT TRUE', 'items')->isKeyword('NOT'));
    }

    public function testIsKeywordIsFalseForAnotherKeywordEntirely(): void
    {
        self::assertFalse(MySqlUpsertExpressionCursor::over('NOT TRUE', 'items')->isKeyword('AND'));
    }

    public function testIsSymbolReportsOneOfTheSymbolsItWasGiven(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('qty + 1', 'items');
        $cursor->advance();

        self::assertTrue($cursor->isSymbol(['+', '-']));
    }

    public function testIsSymbolIsFalseWhereTheExpressionHasBeenRead(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('1', 'items');
        $cursor->advance();

        self::assertFalse($cursor->isSymbol(['+']));
    }

    public function testIsSymbolAtLooksFurtherAlongWithoutMovingTheCursor(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('a <= 1', 'items');

        self::assertTrue($cursor->isSymbolAt(2, ['=']));
    }

    public function testIsNameReportsThatTheCursorIsOnAName(): void
    {
        self::assertTrue(MySqlUpsertExpressionCursor::over('qty', 'items')->isName());
    }

    public function testIsNameIsFalseWhereTheCursorIsOnALiteral(): void
    {
        self::assertFalse(MySqlUpsertExpressionCursor::over('1', 'items')->isName());
    }

    public function testTakeNameReadsTheNameAndMovesPastIt(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('qty + 1', 'items');

        self::assertSame(['qty', '+'], [$cursor->takeName(), $cursor->token()?->text]);
    }

    public function testTakeNameRefusesAnythingThatIsNotAName(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        MySqlUpsertExpressionCursor::over('1', 'items')->takeName();
    }

    public function testSourceOfReadsTheTablesOwnNameAsTheRowAlreadyThere(): void
    {
        self::assertSame(
            UpsertColumnSource::Existing,
            MySqlUpsertExpressionCursor::over('items.qty', 'items')->sourceOf('items'),
        );
    }

    public function testSourceOfReadsTheAliasAsTheRowBeingWritten(): void
    {
        self::assertSame(
            UpsertColumnSource::Incoming,
            MySqlUpsertExpressionCursor::over('new.qty', 'items', 'new')->sourceOf('new'),
        );
    }

    public function testSourceOfRefusesAQualifierThatNamesNeitherRow(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        MySqlUpsertExpressionCursor::over('other.qty', 'items')->sourceOf('other');
    }

    public function testUnsupportedNamesTheExpressionItIsRefusing(): void
    {
        self::assertSame(
            'qty + 1',
            MySqlUpsertExpressionCursor::over('qty + 1', 'items')->unsupported()->getSql(),
        );
    }
}
