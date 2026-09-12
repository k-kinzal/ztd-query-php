<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\LoadData\ColumnMapping;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[CoversClass(ColumnMapping::class)]
final class ColumnMappingTest extends TestCase
{
    public function testInputTargets(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t (id, @raw)";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name', 'score'], [], [], [], [], generatedExpressions: ['score' => 'id * 2']);
        self::assertSame(['id', '@raw'], (new ColumnMapping())->inputTargets($statement, $definition, $sql));
        $statement->col_name_or_user_var = null;
        self::assertSame(['id', 'name'], (new ColumnMapping())->inputTargets($statement, $definition, $sql));
    }

    public function testInputTarget(): void
    {
        $mapping = new ColumnMapping();
        self::assertSame('name', $mapping->inputTarget('`name`', 'LOAD DATA'));
        self::assertSame('@raw', $mapping->inputTarget('@`raw`', 'LOAD DATA'));
    }

    public function testInputTargetRejectsExpressions(): void
    {
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        (new ColumnMapping())->inputTarget('id + 1', 'LOAD DATA');
    }

    public function testSetOperations(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t SET name = UPPER(@raw)";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $definition = new \ZtdQuery\Schema\TableDefinition(['id', 'name', 'score'], [], [], [], [], generatedExpressions: ['score' => 'id * 2']);
        self::assertSame(['name' => 'UPPER(@raw)'], (new ColumnMapping())->setOperations($statement, $definition, $sql));
    }

    public function testIgnoreRows(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t IGNORE 2 LINES";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        self::assertSame(2, (new ColumnMapping())->ignoreRows($statement, $sql));
        $statement->ignore_number = null;
        self::assertSame(0, (new ColumnMapping())->ignoreRows($statement, $sql));
    }

    public function testOptionValue(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t FIELDS TERMINATED BY ','";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $mapping = new ColumnMapping();
        self::assertSame(',', $mapping->optionValue($statement->fields_options, 'TERMINATED BY', "\t"));
        self::assertSame('fallback', $mapping->optionValue($statement->fields_options, 'ENCLOSED BY', 'fallback'));
        self::assertSame('fallback', $mapping->optionValue(null, 'TERMINATED BY', 'fallback'));
    }

}
