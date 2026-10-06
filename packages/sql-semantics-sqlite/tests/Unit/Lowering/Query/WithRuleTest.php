<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Query\WithRule;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\Materialization;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;

#[CoversClass(WithRule::class)]
#[Medium]
final class WithRuleTest extends TestCase
{
    public function testOptionalIsNullWithoutAWithClauseOnADataChange(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('DELETE FROM t')->statement;
        $with = $semantics->analyze('WITH c AS (SELECT 1) DELETE FROM t WHERE a IN c')->statement;
        $recursive = $semantics->analyze('WITH RECURSIVE c(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM c WHERE n < 3) UPDATE t SET a = 1 WHERE a IN c')->statement;
        $insert = $semantics->analyze('WITH c AS (SELECT 1) INSERT INTO t SELECT * FROM c')->statement;

        self::assertInstanceOf(Delete::class, $bare);
        self::assertInstanceOf(Delete::class, $with);
        self::assertInstanceOf(Update::class, $recursive);
        self::assertInstanceOf(InsertSelect::class, $insert);
        self::assertNull($bare->with);
        self::assertNotNull($with->with);
        self::assertFalse($with->with->recursive);
        self::assertNotNull($recursive->with);
        self::assertTrue($recursive->with->recursive);
        self::assertNotNull($insert->into->with);
        self::assertSame('c', $insert->into->with->tables[0]->name->value);
    }

    public function testOptionalRendersTheClauseBeforeTheDataChange(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('WITH c AS (SELECT 1) DELETE FROM t WHERE a IN c', $semantics->analyze('with c as (select 1) delete from t where a in c')->toString());
        self::assertSame('WITH RECURSIVE c AS (SELECT 1) UPDATE t SET a = 1', $semantics->analyze('WITH RECURSIVE c AS (SELECT 1) UPDATE t SET a = 1')->toString());
    }

    public function testClauseKeepsTheCommonTablesInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('WITH a AS (SELECT 1), b AS (SELECT 2), c AS (SELECT 3) SELECT * FROM a, b, c');

        self::assertInstanceOf(WithQuery::class, $operation->statement);
        self::assertSame(['a', 'b', 'c'], array_map(static fn (CommonTable $table): string => $table->name->value, $operation->statement->with->tables));
        self::assertFalse($operation->statement->with->recursive);
        self::assertSame('WITH a AS (SELECT 1), b AS (SELECT 2), c AS (SELECT 3) SELECT * FROM a, b, c', $operation->toString());
    }

    public function testClauseReadsTheRecursiveKeyword(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('WITH RECURSIVE c(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM c WHERE n < 3) SELECT n FROM c');

        self::assertInstanceOf(WithQuery::class, $operation->statement);
        self::assertTrue($operation->statement->with->recursive);
        self::assertSame('WITH RECURSIVE c (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM c WHERE n < 3) SELECT n FROM c', $operation->toString());
    }

    public function testTableLowersTheNameTheColumnsTheQueryAndTheMaterializationHint(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('WITH c (a, b) AS MATERIALIZED (SELECT 1, 2), d AS NOT MATERIALIZED (SELECT 3), e AS (SELECT 4) SELECT * FROM c, d, e');

        self::assertInstanceOf(WithQuery::class, $operation->statement);
        self::assertSame([[2, Materialization::Materialized], [0, Materialization::NotMaterialized], [0, null]], array_map(static function (CommonTable $table): array {
            self::assertInstanceOf(Select::class, $table->query);

            return [count($table->columns), $table->materialization];
        }, $operation->statement->with->tables));
        self::assertSame('WITH c (a, b) AS MATERIALIZED (SELECT 1, 2), d AS NOT MATERIALIZED (SELECT 3), e AS (SELECT 4) SELECT * FROM c, d, e', $operation->toString());
    }
}
