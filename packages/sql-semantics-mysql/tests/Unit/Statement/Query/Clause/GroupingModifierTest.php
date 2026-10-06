<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;

#[CoversClass(GroupingModifier::class)]
#[Small]
final class GroupingModifierTest extends TestCase
{
    public function testCasesNameTheFourModifiers(): void
    {
        self::assertSame(['WithRollup', 'WithCube', 'Rollup', 'Cube'], array_column(GroupingModifier::cases(), 'name'));
    }
}
