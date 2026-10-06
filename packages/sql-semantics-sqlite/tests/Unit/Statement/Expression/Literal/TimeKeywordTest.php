<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\CurrentTime;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TimeKeyword;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(TimeKeyword::class)]
#[Medium]
final class TimeKeywordTest extends TestCase
{
    public function testFromReadsEachKeyword(): void
    {
        self::assertSame(TimeKeyword::Time, TimeKeyword::from('CURRENT_TIME'));
        self::assertSame(TimeKeyword::Date, TimeKeyword::from('CURRENT_DATE'));
        self::assertSame(TimeKeyword::Timestamp, TimeKeyword::from('CURRENT_TIMESTAMP'));
    }

    public function testCasesListTheThreeKeywords(): void
    {
        self::assertSame(['CURRENT_TIME', 'CURRENT_DATE', 'CURRENT_TIMESTAMP'], array_map(static fn (TimeKeyword $keyword): string => $keyword->value, TimeKeyword::cases()));
    }

    public function testFromMatchesTheKeywordOfAnAnalyzedExpression(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT current_date, CURRENT_TIME')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(CurrentTime::class, $statement->columns[0]->expression);
        self::assertInstanceOf(CurrentTime::class, $statement->columns[1]->expression);
        self::assertSame(TimeKeyword::Date, $statement->columns[0]->expression->keyword);
        self::assertSame(TimeKeyword::Time, $statement->columns[1]->expression->keyword);
    }
}
