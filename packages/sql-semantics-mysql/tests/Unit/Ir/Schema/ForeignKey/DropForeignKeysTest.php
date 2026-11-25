<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;

#[CoversClass(DropForeignKeys::class)]
#[Small]
final class DropForeignKeysTest extends TestCase
{
    public function testToStringRetainsMixedOrderAndDetachesOperands(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('child')), ExternalRelationKind::BaseTable);
        $index = new IndexIdentity($owner, new Identifier('backing_key'), IndexRole::Ordinary);
        $constraint = new ForeignKeyRemoval(new Identifier('parent_ref'), ForeignKeyRemovalKind::Constraint);
        $selected = $index;
        $drop = new DropForeignKeys($owner, [&$selected, $constraint], []);
        $selected = new IndexIdentity($owner, new Identifier('unrelated'), IndexRole::Ordinary);

        self::assertSame('ALTER TABLE `app`.`child` DROP INDEX `backing_key`, DROP FOREIGN KEY `parent_ref`', $drop->toString());
    }

    public function testProfileIdRetainsTheSelectedRelease(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('child')), ExternalRelationKind::BaseTable);
        $constraint = new ForeignKeyRemoval(new Identifier('parent_ref'), ForeignKeyRemovalKind::Constraint);
        $drop = new DropForeignKeys($owner, [$constraint], [], GrammarRelease::MySql847);

        self::assertStringStartsWith('mysql-8.4.7/', $drop->profileId());
    }
}
