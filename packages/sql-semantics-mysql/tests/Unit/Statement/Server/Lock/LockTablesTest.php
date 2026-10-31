<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(LockTables::class)]
#[Medium]
final class LockTablesTest extends TestCase
{
    public function testRenderWritesTheLocks(): void
    {
        self::assertSame('LOCK TABLE t READ, u WRITE', (new Semantics(Dialect::MySql))->analyze('lock table t read, u write')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $lock = $semantics->analyze('LOCK TABLES t READ, u WRITE', [$table]);
        self::assertInstanceOf(LockTables::class, $lock);

        self::assertInstanceOf(DeclaredTable::class, $lock->locks[0]->relationInformation()->table);
        self::assertInstanceOf(MissingTable::class, $lock->locks[1]->relationInformation()->table);
    }

    public function testDeriveStatementReportsAnAliasNamingAnEarlierTableOfTheCurrentDatabase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $lock = $semantics->analyze('LOCK TABLES pbt.t READ, u AS t WRITE', $semantics->context(searchPath: new \SqlSemantics\Contract\SearchPath('pbt')));

        self::assertInstanceOf(NonUniqueTable::class, $lock->diagnostics()[0]);
    }

    public function testDeriveStatementWarnsOfEachLowPriorityWriteIn57(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertCount(2, $semantics->analyze('LOCK TABLES t LOW_PRIORITY WRITE, t AS u LOW_PRIORITY WRITE, t AS v READ')->warnings());
    }
}
