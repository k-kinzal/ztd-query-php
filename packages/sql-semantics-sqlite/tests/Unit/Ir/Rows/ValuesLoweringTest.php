<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesLowering;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(ValuesLowering::class)]
#[Small]
final class ValuesLoweringTest extends TestCase
{
    public function testLowerUsesAnEmptyRelationScope(): void
    {
        $value = (new Semantics())->analyze('VALUES (?02, 1 + 2), (NULL, -3)');

        self::assertInstanceOf(ValuesQuery::class, $value);
        self::assertSame('VALUES (?02, (1 + 2)), (NULL, -3)', $value->toString());
    }
}
