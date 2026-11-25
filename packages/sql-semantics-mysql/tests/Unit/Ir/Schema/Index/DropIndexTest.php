<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(DropIndex::class)]
#[Small]
final class DropIndexTest extends TestCase
{
    public function testToStringQuotesTheResolvedPhysicalIndexAndOwner(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('orders'));
        $owner = new TableIdentity($name, ExternalRelationKind::TemporaryTable);
        $target = new IndexIdentity($owner, new Identifier('key`a'), IndexRole::Unique);
        $drop = new DropIndex($target);

        self::assertSame('DROP INDEX `key``a` ON `app`.`orders`', $drop->toString());
        self::assertSame($owner, $drop->target->owner);
    }

    public function testProfileIdUsesTheExactRelease(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('orders'));
        $owner = new TableIdentity($name, ExternalRelationKind::BaseTable);
        $target = new IndexIdentity($owner, new Identifier('PRIMARY'), IndexRole::Primary);

        $drop = new DropIndex($target, GrammarRelease::MySql5651);
        self::assertSame('mysql-5.6.51/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-index', $drop->profileId());
    }
}
