<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableRule;

#[CoversClass(VariableRule::class)]
#[Small]
final class VariableRuleTest extends TestCase
{
    public function testCodeIsTheServerErrorOfEachRule(): void
    {
        self::assertSame([1238, 1238, 1238, 1228, 1229, 1621], array_map(static fn (VariableRule $rule): int => $rule->code(), VariableRule::cases()));
    }
}
