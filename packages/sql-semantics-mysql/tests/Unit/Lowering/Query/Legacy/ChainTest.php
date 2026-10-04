<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\Chain;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;

#[CoversClass(Chain::class)]
#[Small]
final class ChainTest extends TestCase
{
    public function testQueryLiftsTheClausesOfTheLastBlock(): void
    {
        $last = (new Block([], [new SelectExpression(new NumberLiteral('2'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1'))));
        $query = (new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()], [$last, new Trailer()]], [null], GrammarRelease::MySql5744))->query();

        self::assertInstanceOf(QueryExpression::class, $query);
        self::assertInstanceOf(SetOperation::class, $query->body);
        self::assertNotNull($query->limit);
    }

    public function testQueryOrdersAUnionAfterTheClausesOfItsLastBlock(): void
    {
        $last = (new Block([], [new SelectExpression(new NumberLiteral('2'))], null, new Dual()))->then(new Trailer([], null, null, [new LockingClause(LockStrength::Update)]));
        $query = (new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()], [$last, new Trailer([], new RowLimit(new NumberLiteral('1')))]], [null], GrammarRelease::MySql5651))->query();

        self::assertInstanceOf(OrderedSetOperation::class, $query);
        self::assertCount(1, $query->right->locking);
        self::assertNotNull($query->limit);
    }

    public function testEarlierRejectsALimitBeforeTheLastOperandIn57(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5744);

        $this->expectException(AnalysisException::class);

        $chain->earlier((new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1')))), new Trailer(), 0);
    }

    public function testEarlierKeepsALimitBeforeTheLastOperandIn56(): void
    {
        $limit = new RowLimit(new NumberLiteral('1'));
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5651);
        $query = $chain->earlier((new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], $limit)), new Trailer(), 0);

        self::assertInstanceOf(Select::class, $query);
        self::assertSame($limit, $query->limit);
    }

    public function testEarlierRejectsAnOrderingAfterTheFirstOperandOfASubqueryUnion(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], [], GrammarRelease::MySql5651);

        $this->expectException(AnalysisException::class);

        $chain->earlier(new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer([new OrderItem(new NumberLiteral('1.5'))]), 0);
    }
}
