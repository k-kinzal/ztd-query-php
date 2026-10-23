<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses;

#[CoversClass(ValuesClauses::class)]
#[Medium]
final class ValuesClausesTest extends TestCase
{
    public function testDeriveQueryPreservesWrittenRowsAndUnusedConditions(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = $semantics->analyze('CREATE TABLE t(id INT)')->declarations();
        $operation = $semantics->analyze('INSERT INTO t VALUES ROW(1) ORDER BY ROW(2,3) LIMIT absent', $tables);
        self::assertInstanceOf(M\Dml\Insert\InsertQuery::class, $operation->statement);
        self::assertInstanceOf(ValuesClauses::class, $operation->statement->source);
        self::assertCount(1, $operation->facts->query($operation->statement->source)->shape->slots);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderPreservesEveryClause(): void
    {
        $sql = 'INSERT INTO t WITH x AS (SELECT 1) VALUES ROW(2), ROW(1) ORDER BY ROW(2, 3) DESC LIMIT absent';
        self::assertSame($sql, (new Semantics(Dialect::MySql))->analyze($sql)->toString());
    }
}
