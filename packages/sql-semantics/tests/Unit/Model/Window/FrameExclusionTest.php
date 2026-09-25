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
use SqlSemantics\Model\Window\FrameExclusion;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FrameExclusion::class)]
#[Medium]
final class FrameExclusionTest extends TestCase
{
    public function testRepresentsEveryExclusionPolicy(): void
    {
        self::assertSame(['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'], array_column(FrameExclusion::cases(), 'value'));
    }

    #[TestWith(['', FrameExclusion::None])]
    #[TestWith([' EXCLUDE NO OTHERS', FrameExclusion::None])]
    #[TestWith([' EXCLUDE CURRENT ROW', FrameExclusion::Current])]
    #[TestWith([' EXCLUDE GROUP', FrameExclusion::Group])]
    #[TestWith([' EXCLUDE TIES', FrameExclusion::Ties])]
    public function testClassifiesTheWrittenExclusion(string $suffix, FrameExclusion $exclusion): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)'));
        $query = $binder->bind('SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW' . $suffix . ') FROM t');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        self::assertInstanceOf(WindowSpecification::class, $call->window);
        self::assertSame($exclusion, $call->window->frame?->exclusion);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }
}
