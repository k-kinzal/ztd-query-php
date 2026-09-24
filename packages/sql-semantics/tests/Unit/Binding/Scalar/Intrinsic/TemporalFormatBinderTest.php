<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\TemporalFormatBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Temporal\TemporalFormat;
use SqlSemantics\Model\Scalar\Temporal\TemporalFormatKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TemporalFormatBinder::class)]
#[Medium]
final class TemporalFormatBinderTest extends TestCase
{
    #[TestWith(['DATE', TemporalFormatKind::Date])]
    #[TestWith(['TIME', TemporalFormatKind::Time])]
    #[TestWith(['DATETIME', TemporalFormatKind::Datetime])]
    #[TestWith(['TIMESTAMP', TemporalFormatKind::Datetime])]
    public function testBindReadsTheKindKeyword(string $keyword, TemporalFormatKind $kind): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT GET_FORMAT(' . $keyword . ", 'USA')");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $format = $statement->outputs[0]->expression;
        self::assertInstanceOf(TemporalFormat::class, $format);
        self::assertSame([$kind, 'varchar'], [$format->temporalKind, $format->type->name]);
    }
}
