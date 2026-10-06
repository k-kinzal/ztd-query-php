<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;

#[CoversClass(SortDirection::class)]
#[Small]
final class SortDirectionTest extends TestCase
{
    public function testCasesCarryTheKeywordsSqliteWrites(): void
    {
        self::assertSame(['ASC', 'DESC'], array_map(static fn (SortDirection $direction): string => $direction->value, SortDirection::cases()));
        self::assertSame(SortDirection::Descending, SortDirection::from('DESC'));
    }
}
