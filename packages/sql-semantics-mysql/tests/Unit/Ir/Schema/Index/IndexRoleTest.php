<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;

#[CoversClass(IndexRole::class)]
#[Small]
final class IndexRoleTest extends TestCase
{
    public function testClusteredIdentifiesBothPhysicalRoles(): void
    {
        self::assertTrue(IndexRole::Primary->clustered());
        self::assertTrue(IndexRole::ClusteredUnique->clustered());
        self::assertFalse(IndexRole::Unique->clustered());
        self::assertFalse(IndexRole::Ordinary->clustered());
    }

    public function testNativePoliciesHaveDistinctValues(): void
    {
        self::assertSame(['primary', 'clustered-unique', 'unique', 'ordinary'], array_column(IndexRole::cases(), 'value'));
    }
}
