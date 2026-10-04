<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineOption;

#[CoversClass(RoutineOption::class)]
#[Small]
final class RoutineOptionTest extends TestCase
{
    public function testRoutineOptionsAreClauses(): void
    {
        self::assertContains(Clause::class, class_implements(RoutineAttribute::Stable));
    }

    public function testAlterableTellsWhetherAlterAcceptsTheOption(): void
    {
        self::assertFalse(RoutineAttribute::Window->alterable());
    }

    public function testSettingNamesTheAttribute(): void
    {
        self::assertSame('volatility', RoutineAttribute::Volatile->setting());
    }
}
