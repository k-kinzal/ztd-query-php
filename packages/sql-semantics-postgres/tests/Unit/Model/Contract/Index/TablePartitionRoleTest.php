<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartitionRole;

#[CoversClass(TablePartitionRole::class)]
#[Small]
final class TablePartitionRoleTest extends TestCase
{
    public function testRolesIdentifyTheSeparateNativeCatalogKinds(): void
    {
        self::assertSame([TablePartitionRole::Branch, TablePartitionRole::Leaf], TablePartitionRole::cases());
    }
}
