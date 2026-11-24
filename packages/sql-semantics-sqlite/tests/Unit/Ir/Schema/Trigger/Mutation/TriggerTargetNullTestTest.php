<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Mutation\TriggerTargetNullTest;

#[CoversClass(TriggerTargetNullTest::class)]
#[Small]
final class TriggerTargetNullTestTest extends TestCase
{
    public function testKeepsTheLocalColumnAndNullPolarity(): void
    {
        $slot = new TriggerTargetField(2);
        $test = new TriggerTargetNullTest($slot, false);

        self::assertSame($slot, $test->target);
        self::assertFalse($test->isNull);
    }
}
