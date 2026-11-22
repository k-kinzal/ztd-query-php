<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;

#[CoversClass(TruthTest::class)]
#[Small]
final class TruthTestTest extends TestCase
{
    public function testRetainsTheOperandAndTheRequestedTruth(): void
    {
        $parameter = new Parameter('02');
        $test = new TruthTest($parameter, false, true);

        self::assertSame($parameter, $test->operand);
        self::assertFalse($test->desired);
        self::assertTrue($test->negated);
    }
}
