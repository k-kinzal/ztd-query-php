<?php

declare(strict_types=1);

namespace Tests\Unit\System\Server;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\System\Server\ResourceGroups;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceGroups::class)]
#[Small]
final class ResourceGroupsTest extends TestCase
{
    public function testRowsListsTheUserGroupsFirst(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE RESOURCE GROUP rg TYPE = USER VCPU = 1-2 THREAD_PRIORITY = 5 DISABLE');

        $result1 = $s->query('SELECT * FROM information_schema.RESOURCE_GROUPS')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([['rg', 'USER', '0', '1-2', '5'], ['USR_default', 'USER', '1', '0-7', '0'], ['SYS_default', 'SYSTEM', '1', '0-7', '0']], $result1->rows);
    }
}
