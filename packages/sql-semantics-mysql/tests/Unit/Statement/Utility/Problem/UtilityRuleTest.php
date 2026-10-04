<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule;

#[CoversClass(UtilityRule::class)]
#[Small]
final class UtilityRuleTest extends TestCase
{
    public function testCasesHoldTheMessages(): void
    {
        self::assertSame('EXPLAIN INTO requires FORMAT=JSON', UtilityRule::ExplainIntoFormat->value);
        self::assertCount(5, UtilityRule::cases());
    }
}
