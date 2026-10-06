<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Item;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;

#[CoversClass(PrivilegeKind::class)]
#[Small]
final class PrivilegeKindTest extends TestCase
{
    public function testWordsSplitTheKeywords(): void
    {
        self::assertSame(['CREATE', 'TEMPORARY', 'TABLES'], PrivilegeKind::CreateTemporaryTables->words());
    }

    public function testColumnsTellsTheColumnPrivileges(): void
    {
        self::assertSame(['Select', 'Insert', 'Update', 'References'], array_column(array_values(array_filter(PrivilegeKind::cases(), static fn (PrivilegeKind $kind): bool => $kind->columns())), 'name'));
    }

    public function testTableTellsTheTablePrivileges(): void
    {
        self::assertSame([true, true, false], [PrivilegeKind::Trigger->table(), PrivilegeKind::GrantOption->table(), PrivilegeKind::Execute->table()]);
    }

    public function testRoutineTellsTheRoutinePrivileges(): void
    {
        self::assertSame(['Usage', 'Execute', 'GrantOption', 'AlterRoutine'], array_column(array_values(array_filter(PrivilegeKind::cases(), static fn (PrivilegeKind $kind): bool => $kind->routine())), 'name'));
    }

    public function testDatabaseTellsTheDatabasePrivileges(): void
    {
        self::assertSame([true, true, false, false], [PrivilegeKind::Event->database(), PrivilegeKind::LockTables->database(), PrivilegeKind::CreateUser->database(), PrivilegeKind::Super->database()]);
    }
}
