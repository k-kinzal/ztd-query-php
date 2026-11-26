<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;

#[CoversClass(ColumnKind::class)]
#[Small]
final class ColumnKindTest extends TestCase
{
    public function testSignedIntHasItsNativeStorageName(): void
    {
        self::assertSame('int', ColumnKind::SignedInt->value);
    }

    public function testNullableIsKnownForBothSignedRoles(): void
    {
        self::assertTrue(ColumnKind::SignedInt->nullable());
        self::assertFalse(ColumnKind::RequiredSignedInt->nullable());
    }
}
