<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\LoadData\InputFormat;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\LoadData\ColumnMapping::class)]
#[CoversClass(InputFormat::class)]
final class InputFormatTest extends TestCase
{
    public function testFromStatementDefaults(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $format = InputFormat::fromStatement($statement, $sql);
        self::assertSame("\t", $format->fieldTerminator);
        self::assertSame('', $format->enclosure);
        self::assertSame('\\', $format->escape);
        self::assertSame('', $format->linePrefix);
        self::assertSame("\n", $format->lineTerminator);
    }

    public function testFromStatementCustomSeparators(): void
    {
        $sql = 'LOAD DATA INFILE \'input.csv\' INTO TABLE t FIELDS TERMINATED BY \',\' ENCLOSED BY \'"\' ESCAPED BY \'!\' LINES STARTING BY \'#\' TERMINATED BY \';\'';
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $format = InputFormat::fromStatement($statement, $sql);
        self::assertSame(',', $format->fieldTerminator);
        self::assertSame('"', $format->enclosure);
        self::assertSame('!', $format->escape);
        self::assertSame('#', $format->linePrefix);
        self::assertSame(';', $format->lineTerminator);
    }

    public function testFromStatementRejectsFixedRows(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t FIELDS TERMINATED BY ''";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        InputFormat::fromStatement($statement, $sql);
    }

}
