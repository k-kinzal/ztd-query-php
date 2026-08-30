<?php

declare(strict_types=1);

namespace Tests\Unit\Parse;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Platform\MySql\Parse\MySqlUpsertExpressionCursor;
use ZtdQuery\Platform\MySql\Parse\MySqlUpsertLiteral;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;

#[CoversClass(MySqlUpsertExpressionCursor::class)]
#[UsesClass(MySqlUpsertLiteral::class)]
#[UsesClass(MySqlLexerProfile::class)]
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

    public function testRemainingAnswersEverythingNotReadYet(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('a + b', 'target');

        self::assertCount(3, $cursor->remaining());

        $cursor->advance();

        self::assertCount(2, $cursor->remaining());
    }

    public function testRemainingAnswersNothingOnceEverythingIsRead(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('a', 'target');
        $cursor->advance();

        self::assertSame([], $cursor->remaining());
    }

    public function testInsideBracketsAnswersWhatTheGroupHoldsAndReadsPastIt(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('(a + b) + c', 'target');

        $inside = $cursor->insideBrackets();

        self::assertCount(3, $inside->remaining());
        self::assertCount(2, $cursor->remaining());
    }

    public function testInsideBracketsCountsTheGroupsOfItsOwn(): void
    {
        $cursor = MySqlUpsertExpressionCursor::over('((a))', 'target');

        self::assertCount(3, $cursor->insideBrackets()->remaining());
        self::assertTrue($cursor->atEnd());
    }

    public function testInsideBracketsRefusesWhereNoGroupBegins(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        MySqlUpsertExpressionCursor::over('a', 'target')->insideBrackets();
    }

    public function testInsideBracketsRefusesAGroupThatNeverCloses(): void
    {
        $this->expectException(UnsupportedSqlException::class);

        MySqlUpsertExpressionCursor::over('(a', 'target')->insideBrackets();
    }
}
