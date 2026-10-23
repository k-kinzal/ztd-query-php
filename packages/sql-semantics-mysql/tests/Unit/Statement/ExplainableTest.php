<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Explainable;

#[CoversNothing]
#[Medium]
final class ExplainableTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'query' => ['SELECT 1'];
        yield 'table' => ['TABLE t'];
        yield 'values' => ['VALUES ROW(1)'];
        yield 'insert' => ['INSERT INTO t VALUES (1)'];
        yield 'replace' => ['REPLACE INTO t SET a=1'];
        yield 'update' => ['UPDATE t SET a=1'];
        yield 'delete joined targets' => ['DELETE t, u FROM t JOIN u ON t.a=u.a'];
        yield 'delete using' => ['DELETE FROM t USING t JOIN u ON t.a=u.a'];
        yield 'delete' => ['DELETE FROM t'];
    }

    #[DataProvider('providerStatements')]
    public function testQueriesAndDataChangesHaveTheExplainableRole(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $statement = $semantics->analyze($sql)->statement;
        $explained = $semantics->analyze('EXPLAIN ' . $sql)->statement;

        self::assertInstanceOf(Explainable::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $explained);
        self::assertSame($statement::class, $explained->statement::class);
    }

    public function testAdministrationDoesNotHaveTheExplainableRole(): void
    {
        self::assertNotInstanceOf(Explainable::class, (new Semantics(Dialect::MySql))->analyze('SHOW PLUGINS')->statement);
    }
}
