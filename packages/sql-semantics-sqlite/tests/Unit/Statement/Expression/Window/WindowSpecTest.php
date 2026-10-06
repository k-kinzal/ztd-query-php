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
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(WindowSpec::class)]
#[Medium]
final class WindowSpecTest extends TestCase
{
    public function testReadsEveryPartOfAnAnalyzedWindow(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT rank() OVER (w PARTITION BY a ORDER BY b DESC NULLS LAST ROWS 1 PRECEDING) FROM t WINDOW w AS ()', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[0]->expression);
        self::assertInstanceOf(WindowSpec::class, $statement->columns[0]->expression->over);
        $window = $statement->columns[0]->expression->over;
        self::assertSame('w', $window->base?->value);
        self::assertCount(1, $window->partition);
        self::assertCount(1, $window->order);
        self::assertSame(FrameUnit::Rows, $window->frame?->unit);
        self::assertNull($statement->windows[0]->window->base);
        self::assertSame([], $statement->windows[0]->window->partition);
        self::assertNull($statement->windows[0]->window->frame);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testExpressionsListsPartitionOrderingAndOffsetsInWrittenOrder(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT sum(a) OVER (PARTITION BY b ORDER BY a ROWS BETWEEN 1 PRECEDING AND 2 FOLLOWING) FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[0]->expression);
        self::assertInstanceOf(WindowSpec::class, $statement->columns[0]->expression->over);
        $window = $statement->columns[0]->expression->over;
        $expressions = $window->expressions();
        self::assertCount(4, $expressions);
        self::assertNotNull($window->frame);
        self::assertNotNull($window->frame->end);
        self::assertSame($window->partition[0], $expressions[0]);
        self::assertSame($window->order[0]->expression, $expressions[1]);
        self::assertSame($window->frame->start->offset, $expressions[2]);
        self::assertSame($window->frame->end->offset, $expressions[3]);
        self::assertTrue($operation->facts->covers($expressions[2]));
        self::assertTrue($operation->facts->covers($expressions[3]));
    }

    public function testExpressionsIsEmptyForAnEmptyWindow(): void
    {
        self::assertSame([], (new WindowSpec())->expressions());
        self::assertSame([], (new WindowSpec(new Name('w'), [], [], new Frame(FrameUnit::Rows, new FrameBound(FrameBoundKind::CurrentRow))))->expressions());
    }

    public function testRenderWritesThePartsWithoutParentheses(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('select rank() over () AS c1, rank() over (w) AS c2, rank() over (partition by a, b order by a desc nulls last, b) AS c3, sum(a) over (w order by a rows 1 preceding) AS c4 from t window w as ()');

        self::assertSame('SELECT rank() OVER () AS c1, rank() OVER (w) AS c2, rank() OVER (PARTITION BY a, b ORDER BY a DESC NULLS LAST, b) AS c3, sum(a) OVER (w ORDER BY a ROWS 1 PRECEDING) AS c4 FROM t WINDOW w AS ()', $operation->toString());
    }

    public function testRenderWritesANewlyBuiltSpecification(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $window = new WindowSpec(null, [new IntegerLiteral('1')], [new SortTerm(new IntegerLiteral('2'))], new Frame(FrameUnit::Groups, new FrameBound(FrameBoundKind::CurrentRow)));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new FunctionCall(new Name('sum'), [new IntegerLiteral('1')], false, null, [], null, $window))]));

        self::assertSame('SELECT sum(1) OVER (PARTITION BY 1 ORDER BY 2 GROUPS CURRENT ROW)', $operation->toString());
    }
}
