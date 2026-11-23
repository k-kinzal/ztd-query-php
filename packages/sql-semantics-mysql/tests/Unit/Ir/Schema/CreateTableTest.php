<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;

#[CoversClass(CreateTable::class)]
#[Small]
final class CreateTableTest extends TestCase
{
    public function testProfileIdIdentifiesTheNativeDeclarationProfile(): void
    {
        $name = new Identifier('a');
        $target = new TableIdentity(new TableName(new Identifier('app'), $name), ExternalRelationKind::BaseTable);
        $table = new CreateTable($target, [new TableColumn($name, IntegerStorage::Standard)]);

        self::assertStringStartsWith('mysql-9.1.0/', $table->profileId());
    }

    public function testToStringKeepsDefinitionOrderAndStorage(): void
    {
        $target = new TableIdentity(new TableName(new Identifier('app'), new Identifier('t')), ExternalRelationKind::BaseTable);
        $table = new CreateTable($target, [
            new TableColumn(new Identifier('b'), IntegerStorage::Wide),
            new TableColumn(new Identifier('a'), IntegerStorage::Small, false),
        ], true);

        self::assertSame('CREATE TABLE IF NOT EXISTS `app`.`t` (`b` BIGINT NULL, `a` SMALLINT NOT NULL) ENGINE=InnoDB', $table->toString());
    }

    public function testToStringUsesTheSessionNamespaceOfTheDeclaration(): void
    {
        $name = new Identifier('n');
        $target = new TableIdentity(new TableName(new Identifier('app'), new Identifier('scratch')), ExternalRelationKind::TemporaryTable);
        $table = new CreateTable($target, [new TableColumn($name, IntegerStorage::Standard)], true);

        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS `app`.`scratch` (`n` INTEGER NULL) ENGINE=InnoDB', $table->toString());
    }
}
