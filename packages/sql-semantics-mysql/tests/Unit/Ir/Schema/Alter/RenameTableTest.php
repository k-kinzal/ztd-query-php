<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\MySql\Ir\Schema\Alter\TableRename;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(RenameTable::class)]
#[Small]
final class RenameTableTest extends TestCase
{
    public function testProfileIdUsesTheChosenRelease(): void
    {
        $schema = new Identifier('daily');
        $a = new TableName($schema, new Identifier('a'));
        $b = new TableName($schema, new Identifier('b'));
        $statement = new RenameTable([new TableRename($a, $b, $a)], GrammarRelease::MySql5651);

        self::assertSame('mysql-5.6.51/utf8mb4/lower-case-table-names=0/sql-mode=empty/native', $statement->profileId());
    }

    public function testToStringKeepsAllPositionsInACompleteSwap(): void
    {
        $schema = new Identifier('daily');
        $a = new TableName($schema, new Identifier('a'));
        $b = new TableName($schema, new Identifier('b'));
        $tmp = new TableName($schema, new Identifier('tmp'));
        $statement = new RenameTable([new TableRename($a, $tmp, $a), new TableRename($b, $a, $b), new TableRename($tmp, $b, $a)]);

        self::assertSame('RENAME TABLE `daily`.`a` TO `daily`.`tmp`, `daily`.`b` TO `daily`.`a`, `daily`.`tmp` TO `daily`.`b`', $statement->toString());
    }
}
