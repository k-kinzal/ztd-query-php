<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Rows\CompoundOperator;

#[CoversClass(CompoundOperator::class)]
#[Small]
final class CompoundOperatorTest extends TestCase
{
    public function testDefinesTheNativeSetOperators(): void
    {
        self::assertSame(['UNION', 'INTERSECT', 'EXCEPT'], array_map(static fn (CompoundOperator $kind): string => $kind->value, CompoundOperator::cases()));
    }
}
