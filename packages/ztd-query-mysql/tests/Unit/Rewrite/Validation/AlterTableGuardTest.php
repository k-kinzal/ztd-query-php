<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\Validation\AlterTableGuard;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::class)]
#[CoversClass(AlterTableGuard::class)]
final class AlterTableGuardTest extends TestCase
{
    public function testHasUnsupportedAlterOperationCase1(): void
    {
        $operation = 'ADD COLUMN name TEXT';
        $expected = false;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertSame($expected, (new AlterTableGuard())->hasUnsupportedAlterOperation($statement, 'ALTER TABLE users ' . $operation));
    }

    public function testHasUnsupportedAlterOperationCase2(): void
    {
        $operation = 'ALTER COLUMN name SET DEFAULT 7';
        $expected = true;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertSame($expected, (new AlterTableGuard())->hasUnsupportedAlterOperation($statement, 'ALTER TABLE users ' . $operation));
    }

    public function testHasUnsupportedAlterOperationCase3(): void
    {
        $operation = 'ORDER BY id';
        $expected = true;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertSame($expected, (new AlterTableGuard())->hasUnsupportedAlterOperation($statement, 'ALTER TABLE users ' . $operation));
    }

    public function testHasUnsupportedAlterOperationCase4(): void
    {
        $operation = 'ADD INDEX idx (id)';
        $expected = true;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertSame($expected, (new AlterTableGuard())->hasUnsupportedAlterOperation($statement, 'ALTER TABLE users ' . $operation));
    }

    public function testRejectsOperationCase1(): void
    {
        $operation = 'ADD COLUMN name TEXT';
        $expected = false;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertNotNull($statement->altered);
        self::assertSame($expected, (new AlterTableGuard())->rejectsOperation($statement->altered[0]));
    }

    public function testRejectsOperationCase2(): void
    {
        $operation = 'DROP COLUMN name';
        $expected = false;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertNotNull($statement->altered);
        self::assertSame($expected, (new AlterTableGuard())->rejectsOperation($statement->altered[0]));
    }

    public function testRejectsOperationCase3(): void
    {
        $operation = 'ADD INDEX idx (id)';
        $expected = true;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertNotNull($statement->altered);
        self::assertSame($expected, (new AlterTableGuard())->rejectsOperation($statement->altered[0]));
    }

    public function testRejectsOperationCase4(): void
    {
        $operation = 'DROP INDEX idx';
        $expected = true;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertNotNull($statement->altered);
        self::assertSame($expected, (new AlterTableGuard())->rejectsOperation($statement->altered[0]));
    }

    public function testRejectsOperationCase5(): void
    {
        $operation = 'ENGINE = InnoDB';
        $expected = true;
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ' . $operation))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        self::assertNotNull($statement->altered);
        self::assertSame($expected, (new AlterTableGuard())->rejectsOperation($statement->altered[0]));
    }

}
