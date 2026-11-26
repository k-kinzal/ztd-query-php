<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Exchange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\AlterForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(AlterForeignKeys::class)]
#[Small]
final class AlterForeignKeysTest extends TestCase
{
    public function testToStringKeepsOneAtomicStatementWithTheOwnedActions(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $previous = new Identifier('Previous');
        $removal = new ForeignKeyRemoval($previous, ForeignKeyRemovalKind::Constraint);
        $addition = NativeAdditionFixture::addition('Added', false, 'Added');
        $parent = NativeAdditionFixture::key(NativeAdditionFixture::owner('parent'), 'PRIMARY', IndexRole::Primary, 'id');
        $existing = new ForeignKeyConstraintIdentity($owner, $previous);
        $value = new AlterForeignKeys($owner, [&$removal, $addition], [$parent], [$existing]);

        $removal = new ForeignKeyRemoval(new Identifier('Changed'), ForeignKeyRemovalKind::Constraint);

        self::assertSame('ALTER TABLE `app`.`child` DROP FOREIGN KEY `Previous`, ADD CONSTRAINT `Added` FOREIGN KEY (`owner_id`) REFERENCES `app`.`parent` (`id`) ON UPDATE NO ACTION ON DELETE CASCADE', $value->toString());
    }

    public function testProfileIdIdentifiesTheNativeExchangeMode(): void
    {
        $owner = NativeAdditionFixture::owner('child');
        $prior = new Identifier('Previous');
        $removal = new ForeignKeyRemoval($prior, ForeignKeyRemovalKind::Constraint);
        $parent = NativeAdditionFixture::key(NativeAdditionFixture::owner('parent'), 'PRIMARY', IndexRole::Primary, 'id');
        $root = new AlterForeignKeys($owner, [NativeAdditionFixture::addition('Added', false, 'Added'), $removal], [$parent], [
            new ForeignKeyConstraintIdentity($owner, $prior),
        ]);

        self::assertSame('mysql-9.1.0/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-foreign-keys', $root->profileId());
    }
}
