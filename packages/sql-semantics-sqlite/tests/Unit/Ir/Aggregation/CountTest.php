<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;

#[CoversClass(Count::class)]
#[Small]
final class CountTest extends TestCase
{
    public function testRetainsDistinctFieldAndCollation(): void
    {
        $reference = new ColumnReference(1);
        $value = new Count($reference, Collation::NoCase);

        self::assertSame($reference, $value->argument);
        self::assertSame(Collation::NoCase, $value->distinctCollation);
    }

    public function testRejectsDistinctStar(): void
    {
        $this->expectExceptionMessage('A distinct count requires a field argument.');

        new Count(distinctCollation: Collation::Binary);
    }
}
