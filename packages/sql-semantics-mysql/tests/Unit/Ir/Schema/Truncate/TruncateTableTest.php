<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Truncate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(TruncateTable::class)]
#[Small]
final class TruncateTableTest extends TestCase
{
    public function testProfileIdRetainsTheNativeRelease(): void
    {
        $target = new TableIdentity(new TableName(new Identifier('app'), new Identifier('logs')), ExternalRelationKind::BaseTable);
        $statement = new TruncateTable($target, [], GrammarRelease::MySql5651);

        self::assertSame('mysql-5.6.51/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/foreign-key-checks=1', $statement->profileId());
    }

    public function testToStringQuotesBothPartsWithoutChangingCase(): void
    {
        $target = new TableIdentity(new TableName(new Identifier('App'), new Identifier('some`Logs')), ExternalRelationKind::TemporaryTable);
        $statement = new TruncateTable($target, []);

        self::assertSame($target, $statement->target);
        self::assertSame('TRUNCATE TABLE `App`.`some``Logs`', $statement->toString());
    }
}
