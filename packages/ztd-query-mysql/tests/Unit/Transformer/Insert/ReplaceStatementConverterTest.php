<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Transformer\Insert\ReplaceStatementConverter;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
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
