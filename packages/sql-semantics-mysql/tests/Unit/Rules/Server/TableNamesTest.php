<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\TableNames;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(TableNames::class)]
#[Medium]
final class TableNamesTest extends TestCase
{
    public function testRecordResolvesAndReportsARepeatedName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $lock = $semantics->analyze('LOCK TABLES t READ, t AS u WRITE, t AS u READ', [$table]);
        self::assertInstanceOf(LockTables::class, $lock->statement);

        self::assertInstanceOf(DeclaredTable::class, $lock->facts->relation($lock->statement->locks[1])->table);
        self::assertCount(1, $lock->facts->diagnostics);
        self::assertInstanceOf(NonUniqueTable::class, $lock->facts->diagnostics[0]);
    }
}
