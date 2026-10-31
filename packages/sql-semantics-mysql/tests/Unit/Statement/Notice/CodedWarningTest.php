<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Notice\CodedWarning;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatCondition;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CodedWarning::class)]
#[Small]
final class CodedWarningTest extends TestCase
{
    public function testCodeLetsConsumersReadDifferentConditionKindsThroughOneContract(): void
    {
        $read = static fn (CodedWarning $warning): int => $warning->code();

        self::assertSame([1300, 1703], array_map($read, [new NameConversion(new Name('😀')), new HeartbeatPeriod(HeartbeatCondition::BelowMinimum)]));
    }
}
