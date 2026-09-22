<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\FunctionCall;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\Functions;

#[CoversClass(Functions::class)]
#[Medium]
final class FunctionsTest extends TestCase
{
    /**
     * @param non-empty-list<string> $parts Qualified case-sensitive function identity
     */
    #[TestWith(['SELECT "MiXeD"(1)', ['MiXeD']])]
    #[TestWith(['SELECT "App"."MiXeD"(1)', ['App', 'MiXeD']])]
    #[TestWith(['SELECT "coalesce"(1)', ['coalesce']])]
    #[TestWith(['SELECT "greatest"(1)', ['greatest']])]
    #[TestWith(['SELECT "current_date"()', ['current_date']])]
    #[TestWith(['SELECT "select"(1)', ['select']])]
    public function testWritePreservesCaseSensitiveFunctionNames(string $sql, array $parts): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $call = $statement->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertSame($parts, $call->function->name()->parts);
        self::assertSame($sql, $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(BoundSelect::class, $rebound);
        $roundTrip = $rebound->outputs[0]->expression;
        self::assertInstanceOf(FunctionCall::class, $roundTrip);
        self::assertSame($parts, $roundTrip->function->name()->parts);
    }
}
