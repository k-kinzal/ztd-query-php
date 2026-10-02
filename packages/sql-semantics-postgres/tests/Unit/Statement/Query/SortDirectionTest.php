<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;

#[CoversClass(SortDirection::class)]
#[Small]
final class SortDirectionTest extends TestCase
{
    public function testCasesSpellTheDirections(): void
    {
        self::assertSame(['ASC', 'DESC'], array_map(static fn (SortDirection $direction): string => $direction->value, SortDirection::cases()));
    }
}
