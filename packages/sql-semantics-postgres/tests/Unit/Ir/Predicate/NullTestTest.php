<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;

#[CoversClass(NullTest::class)]
#[Small]
final class NullTestTest extends TestCase
{
    public function testStoresAResolvedOperandAndTheTestDirection(): void
    {
        $column = new ColumnReference(2, 1);
        $test = new NullTest($column, true);

        self::assertSame([$column, true], [$test->operand, $test->negated]);
    }
}
