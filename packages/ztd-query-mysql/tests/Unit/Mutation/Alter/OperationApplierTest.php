<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Alter\OperationApplier;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\PrimaryKeyAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\StoredColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\UnsupportedKeyword::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::class)]
#[CoversClass(OperationApplier::class)]
final class OperationApplierTest extends TestCase
{
    public function testApplyOperation(): void
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
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $applier = new OperationApplier($alter, $registry, 't');
        self::assertSame('t', $applier->applyOperation($create, $op, $store, $definition));
        self::assertIsArray($create->fields);
        self::assertSame(['id', 'name', 'score'], array_map(static fn ($field) => $field->name, $create->fields));
    }

    public function testApplyRenameTable(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT, name TEXT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name'], ['id' => 'INT', 'name' => 'TEXT'], [], [], []);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('t', [['id' => 1, 'name' => 'old']]);
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t RENAME TO renamed'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('t', $definition);
        $applier = new OperationApplier($alter, $registry, 't');
        $applier->applyRenameTable($op, $store);
        self::assertSame([['id' => 1, 'name' => 'old']], $store->get('renamed'));
        self::assertSame([], $store->get('t'));
        self::assertSame($definition, $registry->get('renamed'));
        self::assertFalse($registry->has('t'));
        $empty = new \PhpMyAdmin\SqlParser\Components\AlterOperation();
        $empty->options = new \PhpMyAdmin\SqlParser\Components\OptionsArray();
        self::assertSame('renamed', $applier->applyOperation($create, $empty, $store, $definition));
    }

}
