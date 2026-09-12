<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Alter\PrimaryKeyAlteration;

#[CoversClass(PrimaryKeyAlteration::class)]
final class PrimaryKeyAlterationTest extends TestCase
{
    public function testApplyAddPrimaryKey(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT, code INT)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ADD PRIMARY KEY (`id`, `code`)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        (new PrimaryKeyAlteration())->applyAddPrimaryKey($create, $op);
        self::assertIsArray($create->fields);
        self::assertCount(3, $create->fields);
        self::assertNotNull($create->fields[2]->key);
        self::assertSame('PRIMARY KEY', $create->fields[2]->key->type);
        self::assertSame([['name' => 'id'], ['name' => 'code']], $create->fields[2]->key->columns);
    }

    public function testApplyDropPrimaryKey(): void
    {
        $create = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE t (id INT PRIMARY KEY, code INT, PRIMARY KEY (code))'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $create);
        (new PrimaryKeyAlteration())->applyDropPrimaryKey($create);
        self::assertIsArray($create->fields);
        self::assertCount(2, $create->fields);
        self::assertNotNull($create->fields[0]->options);
        self::assertFalse($create->fields[0]->options->has('PRIMARY KEY'));
    }

}
