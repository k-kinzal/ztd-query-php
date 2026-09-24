<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{StaticPrivilege, list<PrivilegeLevel>}>
     */
    public static function providerPrivileges(): array
    {
        return [
            'Usage' => [StaticPrivilege::Usage, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table, PrivilegeLevel::Routine]],
            'GrantOption' => [StaticPrivilege::GrantOption, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table, PrivilegeLevel::Routine]],
            'Select' => [StaticPrivilege::Select, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Insert' => [StaticPrivilege::Insert, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Update' => [StaticPrivilege::Update, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'References' => [StaticPrivilege::References, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Delete' => [StaticPrivilege::Delete, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Index' => [StaticPrivilege::Index, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Alter' => [StaticPrivilege::Alter, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Create' => [StaticPrivilege::Create, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Drop' => [StaticPrivilege::Drop, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'CreateView' => [StaticPrivilege::CreateView, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'ShowView' => [StaticPrivilege::ShowView, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Trigger' => [StaticPrivilege::Trigger, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Table]],
            'Execute' => [StaticPrivilege::Execute, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Routine]],
            'AlterRoutine' => [StaticPrivilege::AlterRoutine, [PrivilegeLevel::Global, PrivilegeLevel::Database, PrivilegeLevel::Routine]],
            'CreateTemporaryTables' => [StaticPrivilege::CreateTemporaryTables, [PrivilegeLevel::Global, PrivilegeLevel::Database]],
            'LockTables' => [StaticPrivilege::LockTables, [PrivilegeLevel::Global, PrivilegeLevel::Database]],
            'CreateRoutine' => [StaticPrivilege::CreateRoutine, [PrivilegeLevel::Global, PrivilegeLevel::Database]],
            'Event' => [StaticPrivilege::Event, [PrivilegeLevel::Global, PrivilegeLevel::Database]],
            'Reload' => [StaticPrivilege::Reload, [PrivilegeLevel::Global]],
            'Shutdown' => [StaticPrivilege::Shutdown, [PrivilegeLevel::Global]],
            'Process' => [StaticPrivilege::Process, [PrivilegeLevel::Global]],
            'File' => [StaticPrivilege::File, [PrivilegeLevel::Global]],
            'ShowDatabases' => [StaticPrivilege::ShowDatabases, [PrivilegeLevel::Global]],
            'Super' => [StaticPrivilege::Super, [PrivilegeLevel::Global]],
            'ReplicationSlave' => [StaticPrivilege::ReplicationSlave, [PrivilegeLevel::Global]],
            'ReplicationClient' => [StaticPrivilege::ReplicationClient, [PrivilegeLevel::Global]],
            'CreateUser' => [StaticPrivilege::CreateUser, [PrivilegeLevel::Global]],
            'CreateTablespace' => [StaticPrivilege::CreateTablespace, [PrivilegeLevel::Global]],
            'CreateRole' => [StaticPrivilege::CreateRole, [PrivilegeLevel::Global]],
            'DropRole' => [StaticPrivilege::DropRole, [PrivilegeLevel::Global]],
        ];
    }

    /**
     * @param list<PrivilegeLevel> $expected
     */
    #[DataProvider('providerPrivileges')]
    public function testLevelsListEachPrivilegeLevel(StaticPrivilege $privilege, array $expected): void
    {
        self::assertSame($expected, $privilege->levels());
    }
}
