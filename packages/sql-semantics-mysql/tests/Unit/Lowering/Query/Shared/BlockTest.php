<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Block::class)]
#[Small]
final class BlockTest extends TestCase
{
    public function testThenAddsTheClausesWrittenAfterTheBlock(): void
    {
        $limit = new RowLimit(new NumberLiteral('1'));
        $block = (new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], $limit));

        self::assertSame($limit, $block->trailer->limit);
    }

    public function testFinishOrdersTheRowsOfABlockThatLimitsItsRows(): void
    {
        $block = (new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1'))));
        $query = $block->finish(new Trailer([new OrderItem(new ColumnUse(new Name('a')))]));

        self::assertInstanceOf(QueryExpression::class, $query);
        self::assertInstanceOf(Select::class, $query->body);
        self::assertCount(1, $query->orderBy);
    }

    public function testFinishKeepsALaterOrderingAfterTheLockingClausesAsTheBlocksOwn(): void
    {
        $block = (new Block([], [new SelectExpression(new NumberLiteral('1'))], null, new Dual()))->then(new Trailer([], null, null, [new LockingClause(LockStrength::Update)]));
        $query = $block->finish(new Trailer([new OrderItem(new ColumnUse(new Name('a')))]));

        self::assertInstanceOf(Select::class, $query);
        self::assertSame([], $query->orderBy);
        self::assertNotNull($query->late);
        self::assertCount(1, $query->late->orderBy);
    }

    public function testFinishKeepsASecondLimitAsTheLimitThatReplacesTheFirst(): void
    {
        $first = new RowLimit(new NumberLiteral('1'));
        $second = new RowLimit(new NumberLiteral('2'));
        $query = (new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], $first))->finish(new Trailer([], $second));

        self::assertInstanceOf(Select::class, $query);
        self::assertSame($first, $query->limit);
        self::assertSame($second, $query->late?->limit);
    }

    public function testFinishWritesClausesInPlaceWhenNothingOfTheBlockPrecedesThem(): void
    {
        $limit = new RowLimit(new NumberLiteral('2'));
        $query = (new Block([], [new SelectExpression(new NumberLiteral('1'))]))->finish(new Trailer([], $limit));

        self::assertInstanceOf(Select::class, $query);
        self::assertSame($limit, $query->limit);
        self::assertNull($query->late);
    }

    public function testBareDropsTheClausesWrittenAfterTheBlock(): void
    {
        $block = (new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1'))));

        self::assertTrue($block->bare()->trailer->empty());
    }

    public function testSelectReadsATrailingIntoWithoutClausesAsTheIntoAfterTheSelectList(): void
    {
        $into = new IntoVariables([new UserVariable(new Name('x'))]);
        $select = (new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], null, null, [], $into, IntoPosition::AfterQuery))->select();

        self::assertSame($into, $select->into);
        self::assertSame(IntoPosition::AfterItems, $select->intoPosition);
    }

    public function testSelectRejectsTwoIntoClauses(): void
    {
        $block = new Block([], [new SelectExpression(new NumberLiteral('1'))], new IntoVariables([new UserVariable(new Name('x'))]), new Dual(), null, null, null, [], null, new Trailer([], null, null, [], new IntoVariables([new UserVariable(new Name('y'))]), IntoPosition::AfterQuery));

        $this->expectException(AnalysisException::class);

        $block->select();
    }
}
