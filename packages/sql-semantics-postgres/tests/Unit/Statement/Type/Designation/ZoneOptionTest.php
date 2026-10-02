<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Designation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\ZoneOption;

#[CoversClass(ZoneOption::class)]
#[Small]
final class ZoneOptionTest extends TestCase
{
    public function testCasesSpellTheClauses(): void
    {
        self::assertSame(['WITH TIME ZONE', 'WITHOUT TIME ZONE'], array_map(static fn (ZoneOption $zone): string => $zone->value, ZoneOption::cases()));
    }
}
