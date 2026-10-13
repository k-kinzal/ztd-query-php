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
        yield 'delete' => ['DELETE FROM t'];
    }

    #[DataProvider('providerStatements')]
    public function testQueriesAndDataChangesHaveTheExplainableRole(string $sql): void
    {
        self::assertInstanceOf(Explainable::class, (new Semantics(Dialect::MySql))->analyze($sql)->statement);
    }

    public function testAdministrationDoesNotHaveTheExplainableRole(): void
    {
        self::assertNotInstanceOf(Explainable::class, (new Semantics(Dialect::MySql))->analyze('SHOW PLUGINS')->statement);
    }
}
