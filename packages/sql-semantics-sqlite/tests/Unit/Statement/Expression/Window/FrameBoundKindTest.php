<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(FrameBoundKind::class)]
#[Medium]
final class FrameBoundKindTest extends TestCase
{
    public function testCasesListTheFiveKindsInGrammarOrder(): void
    {
        self::assertSame([FrameBoundKind::UnboundedPreceding, FrameBoundKind::Preceding, FrameBoundKind::CurrentRow, FrameBoundKind::Following, FrameBoundKind::UnboundedFollowing], FrameBoundKind::cases());
    }

    public function testCasesMatchTheBoundariesOfAnAnalyzedFrame(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT sum(a) OVER (ROWS BETWEEN UNBOUNDED PRECEDING AND 1 FOLLOWING), sum(a) OVER (ROWS 2 PRECEDING) FROM t')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[0]->expression);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[1]->expression);
        self::assertInstanceOf(WindowSpec::class, $statement->columns[0]->expression->over);
        self::assertInstanceOf(WindowSpec::class, $statement->columns[1]->expression->over);
        self::assertSame(FrameBoundKind::UnboundedPreceding, $statement->columns[0]->expression->over->frame?->start->kind);
        self::assertSame(FrameBoundKind::Following, $statement->columns[0]->expression->over->frame->end?->kind);
        self::assertSame(FrameBoundKind::Preceding, $statement->columns[1]->expression->over->frame?->start->kind);
    }
}
