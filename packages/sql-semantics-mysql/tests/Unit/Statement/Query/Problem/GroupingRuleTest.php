<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\GroupingRule;

#[CoversClass(GroupingRule::class)]
#[Small]
final class GroupingRuleTest extends TestCase
{
    public function testCasesNameTheThreeRules(): void
    {
        self::assertSame(['NotDetermined', 'WithoutGroupBy', 'NotSelected'], array_map(static fn (GroupingRule $rule): string => $rule->name, GroupingRule::cases()));
    }
}
