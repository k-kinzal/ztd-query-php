<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameExclusion;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(FrameExclusion::class)]
#[Medium]
final class FrameExclusionTest extends TestCase
{
    public function testFromReadsEachExclusion(): void
    {
        self::assertSame(FrameExclusion::NoOthers, FrameExclusion::from('NO OTHERS'));
        self::assertSame(FrameExclusion::CurrentRow, FrameExclusion::from('CURRENT ROW'));
        self::assertSame(FrameExclusion::Group, FrameExclusion::from('GROUP'));
        self::assertSame(FrameExclusion::Ties, FrameExclusion::from('TIES'));
    }

    public function testCasesListTheFourExclusions(): void
    {
        self::assertSame(['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], array_map(static fn (FrameExclusion $exclusion): string => $exclusion->value, FrameExclusion::cases()));
    }

    public function testFromMatchesTheExclusionOfAnAnalyzedFrame(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS 1 PRECEDING EXCLUDE TIES), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE NO OTHERS), sum(a) OVER (ROWS 1 PRECEDING) FROM t')->statement;

        self::assertInstanceOf(Select::class, $statement);
        $frames = array_map(static function (object $column): ?FrameExclusion {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(FunctionCall::class, $column->expression);
            self::assertInstanceOf(WindowSpec::class, $column->expression->over);

            return $column->expression->over->frame?->exclusion;
        }, $statement->columns);
        self::assertSame([FrameExclusion::Ties, FrameExclusion::NoOthers, null], $frames);
    }
}
