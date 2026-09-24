<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Privilege\PrivilegeLevel;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;

#[CoversClass(StaticPrivilege::class)]
#[Medium]
final class StaticPrivilegeTest extends TestCase
{
    public function testLevelsAllowUsageAndGrantOptionEverywhere(): void
    {
        self::assertSame([PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table, PrivilegeLevel::Routine], StaticPrivilege::GrantOption->levels());
        self::assertSame(StaticPrivilege::Usage->levels(), StaticPrivilege::GrantOption->levels());
    }

    public function testLevelsKeepTableAndRoutinePrivilegesApart(): void
    {
        self::assertSame([PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table], StaticPrivilege::Trigger->levels());
        self::assertSame([PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Routine], StaticPrivilege::AlterRoutine->levels());
        self::assertSame([PrivilegeLevel::Global, PrivilegeLevel::Database], StaticPrivilege::Event->levels());
        self::assertSame([PrivilegeLevel::Global], StaticPrivilege::DropRole->levels());
    }
}
