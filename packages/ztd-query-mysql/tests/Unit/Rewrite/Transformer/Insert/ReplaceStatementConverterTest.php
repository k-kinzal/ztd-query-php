<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Rewrite\Transformer\Insert\ReplaceStatementConverter;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[CoversClass(ReplaceStatementConverter::class)]
final class ReplaceStatementConverterTest extends TestCase
{
    public function testAsInsert(): void
    {
        self::assertSame("/* REPLACE */ INSERT INTO t VALUES ('REPLACE')", (new ReplaceStatementConverter())->asInsert("/* REPLACE */ replace INTO t VALUES ('REPLACE')"));
    }

    public function testAsInsertRejectsSelect(): void
    {
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        (new ReplaceStatementConverter())->asInsert('SELECT 1');
    }

}
