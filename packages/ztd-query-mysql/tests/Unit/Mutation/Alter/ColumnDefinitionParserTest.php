<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Alter\ColumnDefinitionParser;

#[CoversClass(ColumnDefinitionParser::class)]
final class ColumnDefinitionParserTest extends TestCase
{
    public function testBuildColumnDefinition(): void
    {
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t ADD COLUMN name VARCHAR(20) NOT NULL'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        $column = (new ColumnDefinitionParser())->buildColumnDefinition($op);
        self::assertNotNull($column);
        self::assertSame('name', $column->name);
        self::assertNotNull($column->type);
        self::assertSame('VARCHAR', $column->type->name);
        self::assertSame(['20'], $column->type->parameters);
    }

    public function testBuildColumnDefinitionFromUnknown(): void
    {
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t CHANGE COLUMN name label TEXT'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        $column = (new ColumnDefinitionParser())->buildColumnDefinitionFromUnknown($op);
        self::assertNotNull($column);
        self::assertSame('label', $column->name);
        self::assertNotNull($column->type);
        self::assertSame('TEXT', $column->type->name);
    }

    public function testGetColumnName(): void
    {
        $alter = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE t DROP COLUMN `name`'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $alter);
        self::assertNotNull($alter->altered);
        $op = $alter->altered[0];
        self::assertSame('name', (new ColumnDefinitionParser())->getColumnName($op));
        self::assertNull((new ColumnDefinitionParser())->getColumnName(new \PhpMyAdmin\SqlParser\Components\AlterOperation()));
    }

}
