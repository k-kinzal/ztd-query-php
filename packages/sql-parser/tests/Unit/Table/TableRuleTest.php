<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Table\TableRule;

#[CoversClass(TableRule::class)]
#[Small]
final class TableRuleTest extends TestCase
{
    public function testPropertiesAreKept(): void
    {
        $rule = new TableRule(4, 3, 2, true);

        self::assertSame(4, $rule->lhs);
        self::assertSame(3, $rule->length);
        self::assertSame(2, $rule->ordinal);
        self::assertTrue($rule->hidden);
        self::assertFalse((new TableRule(1, 0, 0))->hidden);
    }
}
