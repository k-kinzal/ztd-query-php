<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\Frame;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameExclusion;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(Frame::class)]
#[Medium]
final class FrameTest extends TestCase
{
    public function testReadsBothEndsAndTheExclusion(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $statement = $semantics->analyze('SELECT sum(a) OVER (ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES), sum(a) OVER (RANGE UNBOUNDED PRECEDING) FROM t', [$create])->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[0]->expression);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[1]->expression);
        self::assertInstanceOf(WindowSpec::class, $statement->columns[0]->expression->over);
        self::assertInstanceOf(WindowSpec::class, $statement->columns[1]->expression->over);
        $between = $statement->columns[0]->expression->over->frame;
        $short = $statement->columns[1]->expression->over->frame;
        self::assertNotNull($between);
        self::assertNotNull($short);
        self::assertSame(FrameUnit::Rows, $between->unit);
        self::assertSame(FrameBoundKind::Preceding, $between->start->kind);
        self::assertSame(FrameBoundKind::CurrentRow, $between->end?->kind);
        self::assertSame(FrameExclusion::Ties, $between->exclusion);
        self::assertSame(FrameUnit::Range, $short->unit);
        self::assertSame(FrameBoundKind::UnboundedPreceding, $short->start->kind);
        self::assertNull($short->end);
        self::assertNull($short->exclusion);
    }

    public function testRenderWritesEveryFormOfFrame(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = $semantics->analyze('select sum(a) over (rows between 1 preceding and current row), sum(a) over (range unbounded preceding exclude no others), sum(a) over (groups between current row and unbounded following exclude current row), sum(a) over (rows between 2 following and 3 following exclude group), sum(a) over (rows 1 preceding exclude ties) from t');

        self::assertSame('SELECT sum(a) OVER (ROWS BETWEEN 1 PRECEDING AND CURRENT ROW), sum(a) OVER (RANGE UNBOUNDED PRECEDING EXCLUDE NO OTHERS), sum(a) OVER (GROUPS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING EXCLUDE CURRENT ROW), sum(a) OVER (ROWS BETWEEN 2 FOLLOWING AND 3 FOLLOWING EXCLUDE GROUP), sum(a) OVER (ROWS 1 PRECEDING EXCLUDE TIES) FROM t', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltFrame(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $frame = new Frame(FrameUnit::Range, new FrameBound(FrameBoundKind::UnboundedPreceding), new FrameBound(FrameBoundKind::Following, new IntegerLiteral('2')), FrameExclusion::NoOthers);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new FunctionCall(new Name('sum'), [new IntegerLiteral('1')], false, null, [], null, new WindowSpec(null, [], [], $frame)))]));

        self::assertSame('SELECT sum(1) OVER (RANGE BETWEEN UNBOUNDED PRECEDING AND 2 FOLLOWING EXCLUDE NO OTHERS)', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}
