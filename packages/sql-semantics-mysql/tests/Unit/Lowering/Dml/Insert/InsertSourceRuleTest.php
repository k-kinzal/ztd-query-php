<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\Insert\InsertSourceRule;

#[CoversClass(InsertSourceRule::class)]
#[Medium]
final class InsertSourceRuleTest extends TestCase
{
    public function testSourceLowersTheColumnLists(): void
    {
        self::assertSame('INSERT INTO t () SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('insert t () select 1')->toString());
        self::assertSame('INSERT INTO t (a) SELECT 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('insert t (a) select 1')->toString());
        self::assertSame('INSERT INTO t (a) VALUES (1)', (new Semantics(Dialect::MySql))->analyze('insert t (a) values (1)')->toString());
    }

    public function testBodyLowersLegacyQueries(): void
    {
        self::assertSame('INSERT INTO t (SELECT 1) UNION SELECT 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('insert t (select 1) union select 2')->toString());
        self::assertSame('INSERT INTO t SELECT 1 UNION SELECT 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('insert t select 1 union select 2')->toString());
    }

    public function testValuedLowersValueAndValues(): void
    {
        self::assertSame('INSERT INTO t VALUES (1)', (new Semantics(Dialect::MySql))->analyze('insert t value (1)')->toString());
    }

    public function testRowsLowersEveryRow(): void
    {
        self::assertSame('INSERT INTO t VALUES (1), (2)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('insert t values (1), (2)')->toString());
    }

    public function testColumnsLowersColumnsAndStars(): void
    {
        self::assertSame('INSERT INTO t (a, t.*) VALUES (1, 2)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('insert t (a, t.*) values (1, 2)')->toString());
    }
}
