<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\DropTable;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\AbsentDropTarget;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(DropTable::class)]
#[Small]
final class DropTableTest extends TestCase
{
    public function testProfileIdIdentifiesTheNativeRelease(): void
    {
        $name = new TableName(new Identifier('app'), new Identifier('a'));
        $drop = new DropTable([new TableIdentity($name, ExternalRelationKind::BaseTable)], []);

        self::assertStringStartsWith('mysql-9.1.0/', $drop->profileId());
    }

    public function testToStringRetainsTheOrderedTargets(): void
    {
        $schema = new Identifier('app');
        $first = new TableIdentity(new TableName($schema, new Identifier('b')), ExternalRelationKind::TemporaryTable);
        $second = new AbsentDropTarget(new TableName($schema, new Identifier('a')));
        $drop = new DropTable([$first, $second], [], true, temporaryOnly: true);

        self::assertSame('DROP TEMPORARY TABLE IF EXISTS `app`.`b`, `app`.`a`', $drop->toString());
    }
}
