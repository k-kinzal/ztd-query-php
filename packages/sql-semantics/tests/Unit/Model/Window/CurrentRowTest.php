<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Window\CurrentRow;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CurrentRow::class)]
#[Medium]
final class CurrentRowTest extends TestCase
{
    public function testExpressionsAreEmpty(): void
    {
        self::assertSame([], (new CurrentRow())->expressions());
    }

    public function testCompletesASingleBoundFrameAtTheCurrentRow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER NOT NULL)'));
        $query = $binder->bind('SELECT sum(id) OVER (ORDER BY id ROWS 2 PRECEDING) FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        self::assertInstanceOf(CurrentRow::class, $call->window->frame?->end);
        self::assertSame('SELECT sum(`id`) OVER (ORDER BY `id` ASC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) FROM `t`', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
