<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Alter\CreateTableRenderer;

#[CoversClass(CreateTableRenderer::class)]
final class CreateTableRendererTest extends TestCase
{
    public function testBuildCreateTableSql(): void
    {
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'TEXT'], ['id'], ['id'], ['uq_name' => ['name']]);
        self::assertSame('CREATE TABLE `t` (`id` INT NOT NULL PRIMARY KEY, `name` TEXT, UNIQUE KEY `uq_name` (`name`))', (new CreateTableRenderer('t'))->buildCreateTableSql($definition));
    }

    public function testBuildCreateTableSqlWithCompositeAndForeignKeys(): void
    {
        $foreignKey = new \ZtdQuery\Schema\ForeignKeyDefinition(['id'], 'parent', ['id']);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'code'], [], ['id', 'code'], [], [], foreignKeys: ['fk' => $foreignKey]);
        self::assertSame('CREATE TABLE `t` (`id` TEXT, `code` TEXT, PRIMARY KEY (`id`, `code`), CONSTRAINT `fk` FOREIGN KEY (`id`) REFERENCES `parent` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION)', (new CreateTableRenderer('t'))->buildCreateTableSql($definition));
    }

}
