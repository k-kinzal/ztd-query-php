<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Platform\Postgres\Parse\PgSqlUpsertExpressionCursor;
use ZtdQuery\Platform\Postgres\Parse\PgSqlUpsertLiteral;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;

#[CoversClass(PgSqlUpsertExpressionCursor::class)]
#[UsesClass(PgSqlUpsertLiteral::class)]
#[UsesClass(PgSqlLexerProfile::class)]
final class PgSqlUpsertExpressionCursorTest extends TestCase
{
    public function testOverStartsAtTheFirstThingTheExpressionSays(): void
    {
        self::assertSame('qty', PgSqlUpsertExpressionCursor::over('qty + 1', 'items')->token()?->text);
    }

    public function testTokenIsNothingPastTheEndOfTheExpression(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('1', 'items');
        $cursor->advance();

        self::assertNull($cursor->token());
    }

    public function testTokenAtLooksFurtherAlongWithoutMovingTheCursor(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('qty + 1', 'items');

        self::assertSame(['+', 'qty'], [$cursor->tokenAt(1)?->text, $cursor->token()?->text]);
    }

    public function testAdvanceMovesPastAsManyTokensAsItIsTold(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('qty + 1', 'items');
        $cursor->advance(2);

        self::assertSame('1', $cursor->token()?->text);
    }

    public function testAtEndIsFalseWhileAnythingIsLeft(): void
    {
        self::assertFalse(PgSqlUpsertExpressionCursor::over('1', 'items')->atEnd());
    }

    public function testAtEndReportsThatTheWholeExpressionHasBeenRead(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('1', 'items');
        $cursor->advance();

        self::assertTrue($cursor->atEnd());
    }

    public function testIsKeywordReportsTheKeywordTheCursorIsOn(): void
    {
        self::assertTrue(PgSqlUpsertExpressionCursor::over('NOT TRUE', 'items')->isKeyword('NOT'));
    }

    public function testIsKeywordIsFalseForAnotherKeywordEntirely(): void
    {
        self::assertFalse(PgSqlUpsertExpressionCursor::over('NOT TRUE', 'items')->isKeyword('AND'));
    }

    public function testIsSymbolReportsOneOfTheSymbolsItWasGiven(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('qty + 1', 'items');
        $cursor->advance();

        self::assertTrue($cursor->isSymbol(['+', '-']));
    }

    public function testIsSymbolIsFalseWhereTheExpressionHasBeenRead(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('1', 'items');
        $cursor->advance();

        self::assertFalse($cursor->isSymbol(['+']));
    }

    public function testIsSymbolAtLooksFurtherAlongWithoutMovingTheCursor(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('a <= 1', 'items');

        self::assertTrue($cursor->isSymbolAt(2, ['=']));
    }

    public function testIsNameReportsThatTheCursorIsOnAName(): void
    {
        self::assertTrue(PgSqlUpsertExpressionCursor::over('qty', 'items')->isName());
    }

    public function testIsNameIsFalseWhereTheCursorIsOnALiteral(): void
    {
        self::assertFalse(PgSqlUpsertExpressionCursor::over('1', 'items')->isName());
    }

    public function testTakeNameReadsTheNameAndMovesPastIt(): void
    {
        $cursor = PgSqlUpsertExpressionCursor::over('qty + 1', 'items');

        self::assertSame(['qty', '+'], [$cursor->takeName(), $cursor->token()?->text]);
    }

    public function testTakeNameRefusesAnythingThatIsNotAName(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        PgSqlUpsertExpressionCursor::over('1', 'items')->takeName();
    }

    public function testSourceOfReadsTheTablesOwnNameAsTheRowAlreadyThere(): void
    {
        self::assertSame(
            UpsertColumnSource::Existing,
            PgSqlUpsertExpressionCursor::over('items.qty', 'items')->sourceOf('items'),
        );
    }

    public function testSourceOfReadsTheAliasAsTheRowBeingWritten(): void
    {
        self::assertSame(
            UpsertColumnSource::Incoming,
            PgSqlUpsertExpressionCursor::over('new.qty', 'items', 'new')->sourceOf('new'),
        );
    }

    public function testSourceOfRefusesAQualifierThatNamesNeitherRow(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        PgSqlUpsertExpressionCursor::over('other.qty', 'items')->sourceOf('other');
    }

    public function testUnsupportedNamesTheExpressionItIsRefusing(): void
    {
        self::assertSame(
            'qty + 1',
            PgSqlUpsertExpressionCursor::over('qty + 1', 'items')->unsupported()->getSql(),
        );
    }
}
