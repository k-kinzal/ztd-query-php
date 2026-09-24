<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Window\FrameUnit;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FrameUnit::class)]
#[Medium]
final class FrameUnitTest extends TestCase
{
    public function testRepresentsEveryFrameUnit(): void
    {
        self::assertSame(['ROWS', 'RANGE', 'GROUPS'], array_column(FrameUnit::cases(), 'value'));
    }

    #[TestWith(['ROWS', FrameUnit::Rows])]
    #[TestWith(['RANGE', FrameUnit::Range])]
    #[TestWith(['GROUPS', FrameUnit::Groups])]
    public function testClassifiesTheWrittenUnit(string $unit, FrameUnit $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)'));
        $query = $binder->bind('SELECT sum(id) OVER (ORDER BY id ' . $unit . ' BETWEEN 1 PRECEDING AND 1 FOLLOWING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        self::assertSame($expected, $call->window->frame?->unit);
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
