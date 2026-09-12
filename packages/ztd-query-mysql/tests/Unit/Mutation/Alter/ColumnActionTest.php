<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAction;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\UnsupportedKeyword::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::class)]
#[CoversClass(ColumnAction::class)]
final class ColumnActionTest extends TestCase
{
    public function testDetectCase1(): void
    {
        $operation = 'ADD COLUMN name TEXT';
        $expected = 'add';
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

    public function testDetectCase2(): void
    {
        $operation = 'ADD name TEXT';
        $expected = 'add';
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

    public function testDetectCase3(): void
    {
        $operation = 'DROP COLUMN name';
        $expected = 'drop';
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

    public function testDetectCase4(): void
    {
        $operation = 'DROP name';
        $expected = 'drop';
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

    public function testDetectCase5(): void
    {
        $operation = 'MODIFY COLUMN name VARCHAR(20)';
        $expected = 'modify';
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

    public function testDetectCase6(): void
    {
        $operation = 'CHANGE COLUMN name label TEXT';
        $expected = 'change';
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

    public function testDetectCase7(): void
    {
        $operation = 'ADD PRIMARY KEY (id)';
        $expected = null;
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

    public function testDetectCase8(): void
    {
        $operation = 'DROP PRIMARY KEY';
        $expected = null;
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame($expected, (new ColumnAction())->detect($op));
    }

}
