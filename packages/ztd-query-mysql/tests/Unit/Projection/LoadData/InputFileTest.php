<?php

declare(strict_types=1);

namespace Tests\Unit\Projection\LoadData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Projection\LoadData\InputFile;

#[CoversClass(InputFile::class)]
final class InputFileTest extends TestCase
{
    public function testRead(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mysql-load-');
        self::assertNotFalse($path);
        try {
            file_put_contents($path, "1,hello\n2,world\n");
            $sql = "LOAD DATA LOCAL INFILE '" . $path . "' INTO TABLE t";
            $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
            self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
            self::assertSame("1,hello\n2,world\n", (new InputFile())->read($statement, $sql));
        } finally {
            unlink($path);
        }
    }

    public function testReadRejectsMissingFile(): void
    {
        $sql = "LOAD DATA INFILE 'input.csv' INTO TABLE t";
        $statement = (new \PhpMyAdmin\SqlParser\Parser($sql))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\LoadStatement::class, $statement);
        $statement->file_name = null;
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        (new InputFile())->read($statement, $sql);
    }

}
