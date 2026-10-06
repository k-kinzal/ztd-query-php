<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege\Level;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Small]
final class PrivilegeLevelTest extends TestCase
{
    public function testEveryLevelImplementsTheInterface(): void
    {
        self::assertContainsOnlyInstancesOf(PrivilegeLevel::class, [new GlobalLevel(), new CurrentDatabaseLevel(), new DatabaseLevel(new Name('db')), new ObjectLevel(new QualifiedName(new Name('t')))]);
    }

    public function testDescribeNamesEveryLevel(): void
    {
        self::assertSame(['the global level', 'database db'], array_map(static fn (PrivilegeLevel $level): string => $level->describe(), [new GlobalLevel(), new DatabaseLevel(new Name('db'))]));
    }
}
