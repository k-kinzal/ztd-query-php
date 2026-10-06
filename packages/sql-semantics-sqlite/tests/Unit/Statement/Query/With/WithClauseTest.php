<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;

#[CoversClass(WithClause::class)]
#[Medium]
final class WithClauseTest extends TestCase
{
    public function testRenderWritesRecursiveAndEveryTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $recursive = $semantics->analyze('with recursive c as (select 1), d as (select 2) select * from c, d');
        $plain = $semantics->analyze('with c as (select 1) select * from c');

        self::assertInstanceOf(WithQuery::class, $recursive->statement);
        self::assertTrue($recursive->statement->with->recursive);
        self::assertCount(2, $recursive->statement->with->tables);
        self::assertSame('WITH RECURSIVE c AS (SELECT 1), d AS (SELECT 2) SELECT * FROM c, d', $recursive->toString());
        self::assertInstanceOf(WithQuery::class, $plain->statement);
        self::assertFalse($plain->statement->with->recursive);
        self::assertSame('WITH c AS (SELECT 1) SELECT * FROM c', $plain->toString());
    }

    public function testRenderWritesTablesThatRepeatANameAndReportsThem(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('WITH c AS (SELECT 1), c AS (SELECT 2) SELECT * FROM c');

        self::assertSame('WITH c AS (SELECT 1), c AS (SELECT 2) SELECT * FROM c', $query->toString());
        self::assertSame(MisuseRule::DuplicateCommonTable->value, $query->facts->diagnostics[0]->message());
    }

    public function testRenderRefusesAClauseWithoutTables(): void
    {
        $this->expectExceptionMessage('A WITH clause has at least one common table expression.');

        new WithClause([]);
    }
}
