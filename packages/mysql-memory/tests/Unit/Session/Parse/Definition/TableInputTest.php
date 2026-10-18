<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Definition\TableInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableInput::class)]
#[Medium]
final class TableInputTest extends TestCase
{
    public function testPartitionReportsContextualRestrictionsBeforeEngineFallback(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode=''");
        $session->run('CREATE TABLE t(a INT) ENGINE=missing PARTITION BY HASH(RAND()+ROW(1,2))');

        self::assertSame([['Warning', 1286, "Unknown storage engine 'missing'"], ['Error', 1064, "Constant, random or timezone-dependent expressions in (sub)partitioning function are not allowed near 'RAND()+ROW(1,2))' at line 1"]], $session->diagnostics->conditions);
    }

    public function testRefusedPreservesEngineSubstitutionBeforeThePartitionRole(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode=''");
        $session->run('CREATE TABLE t(a INT) ENGINE=missing PARTITION BY HASH(ROW(missing,2))');

        self::assertSame([['Warning', 1286, "Unknown storage engine 'missing'"], ['Warning', 1266, "Using storage engine InnoDB for table 't'"], ['Error', 1564, 'This partition function is not allowed']], $session->diagnostics->conditions);
        self::assertNull($session->instance->dictionary->table('d', 't'));
    }

    public function testRefusedKeepsGrammarConditionsBeforeEngineSelection(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $session->run('CREATE TABLE t(a INT) ENGINE=missing PARTITION BY HASH(1 COLLATE missing + ROW(1,2))');

        self::assertSame([['Error', 1273, "Unknown collation: 'missing'"]], $session->diagnostics->conditions);
    }

    public function testRefusedKeepsTheCompleteQueryBehindTheEngineCondition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');
        $session->run('CREATE TABLE t(a INT) ENGINE=missing AS SELECT ROW(1,2) FROM absent');

        self::assertSame([['Error', 1286, "Unknown storage engine 'missing'"]], $session->diagnostics->conditions);
        $session->query("SET sql_mode=''");
        $session->run('CREATE TABLE t(a INT) ENGINE=missing AS SELECT ROW(1,2) FROM absent');
        self::assertSame([['Warning', 1286, "Unknown storage engine 'missing'"], ['Warning', 1266, "Using storage engine InnoDB for table 't'"], ['Error', 1146, "Table 'd.absent' doesn't exist"]], $session->diagnostics->conditions);
    }
}
