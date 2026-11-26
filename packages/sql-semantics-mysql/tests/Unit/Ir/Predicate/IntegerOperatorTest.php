<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;

#[CoversClass(IntegerOperator::class)]
#[Small]
final class IntegerOperatorTest extends TestCase
{
    public function testCasesFixTheNativeComparisonIdentities(): void
    {
        self::assertSame(['=', '<>', '<', '<=', '>', '>=', '<=>'], array_column(IntegerOperator::cases(), 'value'));
    }
}
