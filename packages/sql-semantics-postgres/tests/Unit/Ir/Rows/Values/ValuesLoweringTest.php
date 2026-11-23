<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows\Values;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(ValuesLowering::class)]
#[Medium]
final class ValuesLoweringTest extends TestCase
{
    public function testLowerResolvesUntypedNullAlongsideAnExactCast(): void
    {
        $query = (new Semantics())->analyze('VALUES (NULL), (CAST(NULL AS pg_catalog.int8))');

        self::assertInstanceOf(ValuesQuery::class, $query);
        self::assertSame(ColumnKind::Int8, $query->columnKind(0));
    }
}
