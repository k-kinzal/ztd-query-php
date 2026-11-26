<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\Insert\InsertSourceRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InsertSourceRule::class)]
#[Medium]
final class InsertSourceRuleTest extends TestCase
{
    public function testSourceLowersTheColumnLists(): void
    {
        self::assertSame('INSERT INTO t () SELECT 1', (new Inspection('mysql-5.6.51'))->diagnose('insert t () select 1')->toString());
        self::assertSame('INSERT INTO t (a) SELECT 1', (new Inspection('mysql-5.7.44'))->diagnose('insert t (a) select 1')->toString());
        self::assertSame('INSERT INTO t (a) VALUES (1)', (new Inspection())->diagnose('insert t (a) values (1)')->toString());
    }

    public function testBodyLowersLegacyQueries(): void
    {
        self::assertSame('INSERT INTO t (SELECT 1) UNION SELECT 2', (new Inspection('mysql-5.6.51'))->diagnose('insert t (select 1) union select 2')->toString());
        self::assertSame('INSERT INTO t SELECT 1 UNION SELECT 2', (new Inspection('mysql-5.7.44'))->diagnose('insert t select 1 union select 2')->toString());
    }

    public function testValuedLowersValueAndValues(): void
    {
        self::assertSame('INSERT INTO t VALUES (1)', (new Inspection())->diagnose('insert t value (1)')->toString());
    }

    public function testRowsLowersEveryRow(): void
    {
        self::assertSame('INSERT INTO t VALUES (1), (2)', (new Inspection('mysql-5.6.51'))->diagnose('insert t values (1), (2)')->toString());
    }

    public function testColumnsLowersColumnsAndStars(): void
    {
        self::assertSame('INSERT INTO t (a, t.*) VALUES (1, 2)', (new Inspection('mysql-5.7.44'))->diagnose('insert t (a, t.*) values (1, 2)')->toString());
    }
}
