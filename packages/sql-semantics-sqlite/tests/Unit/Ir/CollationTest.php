<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;

#[CoversClass(Collation::class)]
#[Small]
final class CollationTest extends TestCase
{
    public function testDefinesTheClosedBuiltinRoles(): void
    {
        self::assertSame(['BINARY', 'NOCASE', 'RTRIM'], array_map(static fn (Collation $case): string => $case->value, Collation::cases()));
    }
}
