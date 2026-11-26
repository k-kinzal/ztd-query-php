<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Truth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthOperator;

#[CoversClass(TruthOperator::class)]
#[Small]
final class TruthOperatorTest extends TestCase
{
    public function testAllNativeTruthOperationsAreClosed(): void
    {
        self::assertSame(
            ['TRUE', 'FALSE', 'UNKNOWN', 'NOT TRUE', 'NOT FALSE', 'NOT UNKNOWN'],
            array_column(TruthOperator::cases(), 'value')
        );
    }
}
