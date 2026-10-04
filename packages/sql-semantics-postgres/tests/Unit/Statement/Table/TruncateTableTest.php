<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable::class)]
#[Medium]
final class TruncateTableTest extends TestCase
{
    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('TRUNCATE t, u', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable::class, $n1);
        $n2 = $statement->facts->relation($n1->tables[1])->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $n2);
        self::assertSame('SqlSemantics\\Statement\\Reference\\Table\\MissingTable', $n2::class);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('TRUNCATE TABLE ONLY t, s.u CONTINUE IDENTITY RESTRICT', []);
        self::assertSame('TRUNCATE ONLY t, s.u CONTINUE IDENTITY RESTRICT', $statement->toString());
    }
}
