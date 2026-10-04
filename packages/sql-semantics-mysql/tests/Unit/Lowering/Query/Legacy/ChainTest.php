<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\Chain;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Block;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\Trailer;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;

#[CoversClass(Chain::class)]
#[Small]
final class ChainTest extends TestCase
{
    public function testQueryLiftsTheClausesOfTheLastBlock(): void
    {
        $last = (new Block([], [new SelectExpression(new NumberLiteral('2'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1'))));
        $query = (new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()], [$last, new Trailer()]], [null]))->query();

        self::assertInstanceOf(QueryExpression::class, $query);
        self::assertInstanceOf(SetOperation::class, $query->body);
        self::assertNotNull($query->limit);
    }

    public function testCheckRejectsAnOrderingBeforeTheLastOperand(): void
    {
        $chain = new Chain([[new Block([], [new SelectExpression(new NumberLiteral('1'))]), new Trailer()]], []);

        $this->expectException(AnalysisException::class);

        $chain->check((new Block([], [new SelectExpression(new NumberLiteral('1'))]))->then(new Trailer([], new RowLimit(new NumberLiteral('1')))), 0);
    }
}
