<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Routine;

use MySqlMemory\Instance;
use MySqlMemory\Program\Routine\LoadNotices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadNotices::class)]
#[Medium]
final class LoadNoticesTest extends TestCase
{
    public function testRecordFollowsSessionLoadsAndRoutineInvalidation(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $session->run('CREATE DATABASE d');
        $session->run('CREATE PROCEDURE d.p(x CHAR(3) UNICODE COLLATE ucs2_bin) SELECT COLLATION(x)');
        $session->query("CALL d.p('x')");
        $first = array_column($session->diagnostics->conditions, 1);
        $session->query("CALL d.p('x')");
        $cached = $session->diagnostics->conditions;
        $session->run('FLUSH TABLES');
        $session->query("CALL d.p('x')");
        $flushed = $session->diagnostics->conditions;
        $session->run("ALTER PROCEDURE d.p COMMENT 'changed'");
        $session->query("CALL d.p('x')");
        $changed = array_column($session->diagnostics->conditions, 1);
        $second = $instance->connect();
        $second->query("CALL d.p('x')");

        self::assertSame([4079], $first);
        self::assertSame([], $cached);
        self::assertSame([], $flushed);
        self::assertSame([4079], $changed);
        self::assertSame([4079], array_column($second->diagnostics->conditions, 1));
    }
    public function testRecordLoadsAnUnreachedQueryCacheNoticeOnceUntilRoutineDdl(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; CREATE PROCEDURE d.p() BEGIN IF FALSE THEN FLUSH QUERY CACHE; END IF; END');
        $session->query('SELECT 1; CALL d.p()');
        $first = array_column($session->diagnostics->conditions, 1);
        $session->query('SELECT 1; CALL d.p()');
        $cached = $session->diagnostics->conditions;
        $session->query("ALTER PROCEDURE d.p COMMENT 'changed'; SELECT 1; CALL d.p()");

        self::assertSame([1681], $first);
        self::assertSame([], $cached);
        self::assertSame([1681], array_column($session->diagnostics->conditions, 1));
    }

}
