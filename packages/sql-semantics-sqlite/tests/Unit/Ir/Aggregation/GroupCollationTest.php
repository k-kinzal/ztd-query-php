<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupCollation;

#[CoversClass(GroupCollation::class)]
#[Small]
final class GroupCollationTest extends TestCase
{
    public function testDefinesTheClosedBuiltinRoles(): void
    {
        self::assertSame(['BINARY', 'NOCASE', 'RTRIM'], array_map(static fn (GroupCollation $case): string => $case->value, GroupCollation::cases()));
    }
}
