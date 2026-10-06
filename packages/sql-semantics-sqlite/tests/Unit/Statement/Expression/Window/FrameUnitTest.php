<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(FrameUnit::class)]
#[Medium]
final class FrameUnitTest extends TestCase
{
    public function testFromReadsEachUnit(): void
    {
        self::assertSame(FrameUnit::Range, FrameUnit::from('RANGE'));
        self::assertSame(FrameUnit::Rows, FrameUnit::from('ROWS'));
        self::assertSame(FrameUnit::Groups, FrameUnit::from('GROUPS'));
    }

    public function testCasesListTheThreeUnits(): void
    {
        self::assertSame(['RANGE', 'ROWS', 'GROUPS'], array_map(static fn (FrameUnit $unit): string => $unit->value, FrameUnit::cases()));
    }

    public function testFromMatchesTheUnitOfAnAnalyzedFrame(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (rows 1 PRECEDING), sum(a) OVER (RANGE 1 PRECEDING), sum(a) OVER (GROUPS 1 PRECEDING) FROM t')->statement;

        self::assertInstanceOf(Select::class, $statement);
        $units = array_map(static function (object $column): ?FrameUnit {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);
            self::assertInstanceOf(WindowSpec::class, $column->expression->over);

            return $column->expression->over->frame?->unit;
        }, $statement->columns);
        self::assertSame([FrameUnit::Rows, FrameUnit::Range, FrameUnit::Groups], $units);
    }
}
