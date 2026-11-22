<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\CreateTable;
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
        $table = new CreateTable(new TableName(new Identifier('app'), $name), [new TableColumn($name, IntegerStorage::Standard)]);

        self::assertStringStartsWith('mysql-9.1.0/', $table->profileId());
    }

    public function testToStringKeepsDefinitionOrderAndStorage(): void
    {
        $table = new CreateTable(new TableName(new Identifier('app'), new Identifier('t')), [
            new TableColumn(new Identifier('b'), IntegerStorage::Wide),
            new TableColumn(new Identifier('a'), IntegerStorage::Small, false),
        ], true);

        self::assertSame('CREATE TABLE IF NOT EXISTS `app`.`t` (`b` BIGINT NULL, `a` SMALLINT NOT NULL) ENGINE=InnoDB', $table->toString());
    }
}
