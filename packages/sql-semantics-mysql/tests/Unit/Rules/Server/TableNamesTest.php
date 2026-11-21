<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\Server\TableNames;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;

#[CoversClass(TableNames::class)]
#[Medium]
final class TableNamesTest extends TestCase
{
    public function testRecordResolvesAndReportsARepeatedName(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INT)');
        $lock = $semantics->inspect('LOCK TABLES t READ, t AS u WRITE, t AS u READ', [$table]);
        self::assertInstanceOf(LockTables::class, $lock->statement);

        self::assertInstanceOf(DeclaredTable::class, $lock->facts->relation($lock->statement->locks[1])->table);
        self::assertCount(1, $lock->facts->diagnostics);
        self::assertInstanceOf(NonUniqueTable::class, $lock->facts->diagnostics[0]);
    }
}
