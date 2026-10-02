<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\Rendering\SqliteTrivia;
use SqlSemantics\Statement\Expression\SqliteBinaryOperator;

#[CoversClass(SqliteTrivia::class)]
#[Small]
final class SqliteTriviaTest extends TestCase
{
    public function testEndStopsAtTheFollowingSemanticSymbol(): void
    {
        self::assertSame(6, (new SqliteTrivia())->end(' /*x*/id'));
    }

    public function testAcceptsRequiresACompleteGapBeforeTheNextSymbol(): void
    {
        $trivia = new SqliteTrivia();
        self::assertTrue($trivia->accepts(" /*x*/ -- y\n "));
        self::assertFalse($trivia->accepts('-- y'));
        self::assertFalse($trivia->accepts('/* y'));
        self::assertFalse($trivia->accepts(' OR 1 '));
    }

    public function testOperatorKeepsCommentsBetweenWholeOperatorWordsOnly(): void
    {
        $trivia = new SqliteTrivia();
        self::assertTrue($trivia->operator('is /*label*/ not', SqliteBinaryOperator::IsNot));
        self::assertFalse($trivia->operator('i/*label*/s not', SqliteBinaryOperator::IsNot));
        self::assertFalse($trivia->operator('is not 1', SqliteBinaryOperator::IsNot));
    }
}
