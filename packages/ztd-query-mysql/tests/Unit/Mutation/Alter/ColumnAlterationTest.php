<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAlteration;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\StoredColumns::class)]
#[CoversClass(ColumnAlteration::class)]
final class ColumnAlterationTest extends TestCase
{
    public function testApplyAddColumn(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT, name TEXT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'TEXT'], [], [], []);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'old']]);
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ADD COLUMN score INT'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        (new ColumnAlteration($alter, 't'))->applyAddColumn($create, $op, $definition);
        self::assertIsArray($create->fields);
        self::assertSame(['id', 'name', 'score'], array_map(static fn ($field) => $field->name, $create->fields));
    }

    public function testApplyDropColumn(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT, name TEXT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'TEXT'], [], [], []);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'old']]);
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t DROP COLUMN name'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        (new ColumnAlteration($alter, 't'))->applyDropColumn($create, $op, $store, $definition);
        self::assertIsArray($create->fields);
        self::assertSame(['id'], array_map(static fn ($field) => $field->name, $create->fields));
        self::assertSame([['id' => 1]], $store->get('t'));
    }

    public function testApplyModifyColumn(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT, name TEXT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'TEXT'], [], [], []);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'old']]);
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t MODIFY COLUMN name VARCHAR(20)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        (new ColumnAlteration($alter, 't'))->applyModifyColumn($create, $op, $definition);
        self::assertIsArray($create->fields);
        self::assertNotNull($create->fields[1]->type);
        self::assertSame('VARCHAR', $create->fields[1]->type->name);
        self::assertSame(['20'], $create->fields[1]->type->parameters);
    }

    public function testApplyChangeColumn(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT, name TEXT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'TEXT'], [], [], []);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'old']]);
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t CHANGE COLUMN name label VARCHAR(30)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        (new ColumnAlteration($alter, 't'))->applyChangeColumn($create, $op, $store, $definition);
        self::assertIsArray($create->fields);
        self::assertSame('label', $create->fields[1]->name);
        self::assertSame([['id' => 1, 'label' => 'old']], $store->get('t'));
    }

    public function testApplyRenameColumn(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT, name TEXT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'TEXT'], [], [], []);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'old']]);
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t RENAME COLUMN name TO label'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        (new ColumnAlteration($alter, 't'))->applyRenameColumn($create, $op, $store, $definition);
        self::assertIsArray($create->fields);
        self::assertSame('label', $create->fields[1]->name);
        self::assertSame([['id' => 1, 'label' => 'old']], $store->get('t'));
    }

}
