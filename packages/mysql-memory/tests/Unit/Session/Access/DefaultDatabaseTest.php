<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\DefaultDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultDatabase::class)]
#[Small]
final class DefaultDatabaseTest extends TestCase
{
    public function testWriteResolvesItsDatabaseBeforeQueryQuantifierConditions(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('INSERT INTO t SELECT ALL DISTINCT 1');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new DefaultDatabase())->write($operation->statement, $operation->facts, $session);
    }

    public function testWriteLeavesACommonTableReferenceInItsLexicalNamespace(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT)');
        $operation = $session->analyze('WITH c AS (SELECT 1 AS a) UPDATE d.t JOIN c SET t.a=c.a');

        (new DefaultDatabase())->write($operation->statement, $operation->facts, $session);

        self::assertSame('', $session->variables->database);
    }

    public function testReferencedResolvesAnImplicitTableBeforeLockClauseConditions(): void
    {
        $table = new \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
        $query = new \SqlSemantics\Platform\MySql\Statement\Query\QueryStatement($table, [new \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause(\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Update), new \SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause(\SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength::Share)]);
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->text = 'unrelated invalid input';
        $node = new \SqlSemantics\Platform\MySql\Statement\QueryRoot($query);
        $first = (new \Fuzz\Structure\Audit\Memory())->observe($session, $node);
        self::assertSame(1046, $first['error'][0] ?? null);
        $session->variables->database = 'd';
        $second = (new \Fuzz\Structure\Audit\Memory())->observe($session, $node);
        self::assertSame(3569, $second['error'][0] ?? null);
        self::assertSame('unrelated invalid input', $session->text);
    }

    public function testCheckRequiresAViewDatabaseBeforeItsQueryLimits(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('CREATE VIEW v AS SELECT 1 LIMIT n');
    }

    public function testCallsResolvesTheRoutineDatabaseBeforeColumnsAndTables(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT missing, f() FROM d.absent');
        self::assertSame([['Error', 1046, 'No database selected'], ['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testCallsKeepsUnusedCommonTableDefinitionsDeferred(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('WITH c AS (SELECT f()) SELECT 1 AS n')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testReferencedIgnoresAnUnusedCommonTableBody(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('WITH q AS (SELECT 1 FROM missing) SELECT 1');

        (new DefaultDatabase())->referenced($operation, $session);

        self::assertSame('', $session->variables->database);
    }

    public function testReferencedRejectsAReachedTableBeforeHintWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT /*+ RESOURCE_GROUP(missing) */ * FROM missing');

        self::assertSame([['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testCheckRequiresTheDeleteTargetDatabaseBeforeDerivedQueryProblems(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('DELETE x FROM (SELECT HIGH_PRIORITY 1) AS d');
    }

    public function testCheckRequiresTheEventDatabaseBeforeItsBody(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SIGNAL c');
    }

    public function testCheckKeepsQualifiedNamesIndependentOfTheDefaultDatabase(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES (1)');

        $reply = $session->query('DELETE d.t FROM d.t')[0];

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
    }

    public function testCheckLeavesStoredProgramMembersDeferred(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $session->query('CREATE PROCEDURE d.p() DELETE t FROM d.t')[0]);
    }
    public function testCheckRequiresTheLegacyLoadDatabaseBeforeItsCharset(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query("LOAD XML INFILE 'missing' INTO TABLE t CHARSET ``");
    }

    public function testCheckRequiresTheLegacyIndexDatabaseBeforeItsPlugin(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $answer = $session->run('CREATE FULLTEXT INDEX i ON t(a DESC) WITH PARSER missing')[0];
        self::assertInstanceOf(SqlError::class, $answer);
        self::assertSame(1046, $answer->getCode());
    }

}
