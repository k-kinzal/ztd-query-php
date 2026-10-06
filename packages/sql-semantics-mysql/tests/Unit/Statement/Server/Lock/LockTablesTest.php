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
        self::assertSame('LOCK TABLES t READ, u WRITE', (new Semantics(Dialect::MySql))->analyze('lock table t read, u write')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $lock = $semantics->analyze('LOCK TABLES t READ, u WRITE', [$table]);
        self::assertInstanceOf(LockTables::class, $lock->statement);

        self::assertInstanceOf(DeclaredTable::class, $lock->facts->relation($lock->statement->locks[0])->table);
        self::assertInstanceOf(MissingTable::class, $lock->facts->relation($lock->statement->locks[1])->table);
    }

    public function testDeriveStatementReportsARepeatedAlias(): void
    {
        $lock = (new Semantics(Dialect::MySql))->analyze('LOCK TABLES t READ, u AS t WRITE');

        self::assertInstanceOf(NonUniqueTable::class, $lock->facts->diagnostics[0]);
    }
}
