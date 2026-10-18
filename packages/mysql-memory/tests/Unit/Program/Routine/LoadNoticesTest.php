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
    #[\PHPUnit\Framework\Attributes\TestWith(['8.0.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['8.4.7'])]
    public function testPrepareRequiresAnExistingProcedureOnModernReleases(string $release): void
    {
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $call = new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('missing')));
        $this->expectExceptionCode(1305);
        (new LoadNotices())->prepare($call, $session);
    }

    public function testPrepareChecksModernArityWithoutExecutingTheProcedure(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE PROCEDURE p(x INT) SELECT * FROM missing');
        $call = new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')));
        $this->expectExceptionCode(1318);
        (new LoadNotices())->prepare($call, $session);
    }

    public function testPrepareLoadsExistingDefinitionsWithoutCheckingInvocationArity(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
        $call = new \SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')));
        (new LoadNotices())->prepare($call, $session);
        self::assertSame([], $session->diagnostics->conditions);
        $session->query('CREATE PROCEDURE p(x INT) SELECT * FROM t PROCEDURE ANALYSE()');
        $session->diagnostics->clear();
        (new LoadNotices())->prepare($call, $session);
        self::assertSame([1681], array_column($session->diagnostics->conditions, 1));
        $session->diagnostics->clear();
        (new LoadNotices())->prepare($call, $session);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testNoticeKeepsTheLegacyAnalysisWarningOnRoutineLoad(): void
    {
        $clause = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse();
        self::assertSame(1681, (new LoadNotices())->notice($clause, \SqlSemantics\Contract\GrammarRelease::MySql5744)?->code());
        self::assertNull((new LoadNotices())->notice($clause, \SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertNull((new LoadNotices())->notice($clause, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['utf8', 3719])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ucs2', null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['utf8mb3', null])]
    public function testNoticeDistinguishesAliasLoadingFromDeprecation(string $charset, ?int $code): void
    {
        $name = new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name($charset));
        $notice = (new LoadNotices())->notice($name, \SqlSemantics\Contract\GrammarRelease::MySql847);
        self::assertSame($code, $notice?->code());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['(1,2)'])]
    public function testDiscardsLegacyOldPasswordOnlyOnTheFirstLoadAndAfterInvalidation(string $operand): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d; CREATE PROCEDURE d.p() BEGIN IF FALSE THEN SELECT OLD_PASSWORD(' . $operand . '); END IF; END');
        $session->query('CALL d.p()');
        self::assertSame([], $session->diagnostics->conditions);
        $session->query('SELECT OLD_PASSWORD(1); CALL d.p()');
        self::assertSame([1287], array_column($session->diagnostics->conditions, 1));
        $session->query("ALTER PROCEDURE d.p COMMENT 'changed'; SELECT OLD_PASSWORD(1); CALL d.p()");
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testDiscardsAnUnreachedPasswordAssignmentOnInitialLoad(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query("CREATE DATABASE d; CREATE PROCEDURE d.p() BEGIN IF FALSE THEN SET PASSWORD = OLD_PASSWORD('text'); END IF; END");
        $session->query('CALL d.p()');
        self::assertSame([], $session->diagnostics->conditions);
        $session->query('SELECT OLD_PASSWORD(1); CALL d.p()');
        self::assertSame([1287], array_column($session->diagnostics->conditions, 1));
        $session->query("ALTER PROCEDURE d.p COMMENT 'changed'; SELECT OLD_PASSWORD(1); CALL d.p()");
        self::assertSame([], $session->diagnostics->conditions);
    }

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


    public function testReturnedRetainsTheStoredBinaryCollationNotice(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET GLOBAL log_bin_trust_function_creators=1; CREATE FUNCTION d.f() RETURNS CHAR(1) UNICODE BINARY RETURN 'a'");
        $routine = $session->instance->dictionary->schema('d')?->functions['f'];
        self::assertNotNull($routine);
        self::assertSame(4079, (new LoadNotices())->returned($routine, $session->settings()->release())?->code());
        $session->query('SELECT d.f()');
        self::assertSame([4079], array_column($session->diagnostics->conditions, 1));
        $session->query('SELECT d.f()');
        self::assertSame([], $session->diagnostics->conditions);
    }
}
