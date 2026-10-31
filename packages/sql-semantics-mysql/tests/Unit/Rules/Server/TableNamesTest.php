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
        $lock = $semantics->analyze('LOCK TABLES t READ, t AS u WRITE, pbt.t AS u READ', $semantics->context([$table], searchPath: new \SqlSemantics\Contract\SearchPath('pbt')));
        self::assertInstanceOf(LockTables::class, $lock);

        self::assertInstanceOf(DeclaredTable::class, $lock->locks[1]->relationInformation()->table);
        self::assertCount(1, $lock->diagnostics());
        self::assertInstanceOf(NonUniqueTable::class, $lock->diagnostics()[0]);
    }
}
