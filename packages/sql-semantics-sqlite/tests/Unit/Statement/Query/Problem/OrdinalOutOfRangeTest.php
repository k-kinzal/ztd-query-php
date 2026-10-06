<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(OrdinalOutOfRange::class)]
#[Medium]
final class OrdinalOutOfRangeTest extends TestCase
{
    public function testMessageDescribesTheRange(): void
    {
        $problem = new OrdinalOutOfRange(5, 3);

        self::assertSame('Term 5 is out of range - should be between 1 and 3.', $problem->message());
        self::assertSame(5, $problem->ordinal);
        self::assertSame(3, $problem->columns);
        self::assertSame('Term 0 is out of range - should be between 1 and 2.', (new OrdinalOutOfRange(0, 2))->message());
    }

    public function testMessageIsReportedAndResolvesTheTerm(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1, 2 GROUP BY 3');

        self::assertInstanceOf(Select::class, $query->statement);
        $fact = $query->facts->scalar($query->statement->groupBy[0]);
        self::assertInstanceOf(OrdinalOutOfRange::class, $fact->resolution);
        self::assertSame($fact->resolution, $query->facts->diagnostics[0]);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertSame($fact->resolution, $fact->type->cause);
        self::assertSame('Term 3 is out of range - should be between 1 and 2.', $fact->resolution->message());
    }
}
