<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\BindParameter;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ParameterPrefix;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\Frame;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(FrameBound::class)]
#[Medium]
final class FrameBoundTest extends TestCase
{
    public function testReadsTheOffsetOfAnAnalyzedBoundary(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT sum(a) OVER (ROWS BETWEEN 2 PRECEDING AND ? FOLLOWING) FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[0]->expression);
        self::assertInstanceOf(WindowSpec::class, $statement->columns[0]->expression->over);
        $frame = $statement->columns[0]->expression->over->frame;
        self::assertNotNull($frame);
        self::assertInstanceOf(IntegerLiteral::class, $frame->start->offset);
        self::assertSame('2', $frame->start->offset->digits);
        self::assertSame(FrameBoundKind::Following, $frame->end?->kind);
        self::assertInstanceOf(BindParameter::class, $frame->end->offset);
        self::assertTrue($operation->facts->covers($frame->start->offset));
        self::assertTrue($operation->facts->covers($frame->end->offset));
    }

    public function testRenderWritesEveryKindOfBoundary(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select sum(a) over (rows between unbounded preceding and 1 preceding) AS c1, sum(a) over (rows between current row and 1 following) AS c2, sum(a) over (rows between 1 following and unbounded following) AS c3 from t');

        self::assertSame('SELECT sum(a) OVER (ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING) AS c1, sum(a) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS c2, sum(a) OVER (ROWS BETWEEN 1 FOLLOWING AND UNBOUNDED FOLLOWING) AS c3 FROM t', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltBoundary(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $frame = new Frame(FrameUnit::Rows, new FrameBound(FrameBoundKind::Preceding, new BindParameter(ParameterPrefix::Colon, 'n')), new FrameBound(FrameBoundKind::UnboundedFollowing));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new FunctionCall(new Name('sum'), [new IntegerLiteral('1')], false, null, [], null, new WindowSpec(null, [], [], $frame)))]));

        self::assertSame('SELECT sum(1) OVER (ROWS BETWEEN :n PRECEDING AND UNBOUNDED FOLLOWING)', $operation->toString());
    }
}
