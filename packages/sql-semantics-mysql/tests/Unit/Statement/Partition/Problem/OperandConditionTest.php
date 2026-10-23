<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition;

#[CoversClass(OperandCondition::class)]
#[Small]
final class OperandConditionTest extends TestCase
{
    public function testConditionsKeepFunctionPreparationSeparateFromContextualRefusals(): void
    {
        self::assertSame(['Function', 'SessionState', 'Subquery'], array_column(OperandCondition::cases(), 'name'));
    }
}
