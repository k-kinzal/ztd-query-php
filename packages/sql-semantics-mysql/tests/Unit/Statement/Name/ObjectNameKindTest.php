<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName;
use SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind;

#[CoversClass(ObjectNameKind::class)]
#[Small]
final class ObjectNameKindTest extends TestCase
{
    public function testCasesSupplyDistinctObjectRoles(): void
    {
        self::assertSame(["Incorrect database name ''", "Incorrect table name ''"], array_map(static fn (ObjectNameKind $kind): string => (new EmptyObjectName($kind))->message(), ObjectNameKind::cases()));
    }
}
