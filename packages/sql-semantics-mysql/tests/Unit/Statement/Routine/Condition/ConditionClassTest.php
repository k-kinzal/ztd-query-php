<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass;

#[CoversClass(ConditionClass::class)]
#[Small]
final class ConditionClassTest extends TestCase
{
    public function testCasesNameTheThreeClasses(): void
    {
        self::assertSame(['SqlWarning', 'NotFound', 'SqlException'], array_map(static fn (ConditionClass $class): string => $class->name, ConditionClass::cases()));
    }
}
