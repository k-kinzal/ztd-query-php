<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Access\DefaultDatabase;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

#[CoversClass(DefaultDatabase::class)]
#[Small]
final class DefaultDatabaseTest extends TestCase
{
    public function testInspectionUsesTheRetainedTableIdentity(): void
    {
        $session = (new Instance())->connect();
        $name = new QualifiedName(new Name('t'));
        $statement = new ShowColumns(new InspectedTable($name));
        self::assertSame([$name], (new DefaultDatabase())->inspection($statement, $session));
        self::assertSame([], (new DefaultDatabase())->inspection($statement, (new Instance('5.7.44'))->connect()));
    }

    public function testCheckRequiresAnInspectionDatabaseBeforeItsFilter(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1046);
        $session->query('SHOW COLUMNS FROM t WHERE @@no_such_variable');
    }

    public function testCheckUsesAnExplicitInspectionDatabaseWithoutOpeningIt(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1193);
        $session->query('SHOW KEYS FROM t FROM missing WHERE @@no_such_variable');
    }

    public function testWriteResolvesItsDatabaseBeforeQueryQuantifierConditions(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('INSERT INTO t SELECT ALL DISTINCT 1');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);

        (new DefaultDatabase())->write($operation, $operation->statementInformation(), $session);
    }

    public function testWriteLeavesACommonTableReferenceInItsLexicalNamespace(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT)');
        $operation = $session->analyze('WITH c AS (SELECT 1 AS a) UPDATE d.t JOIN c SET t.a=c.a');

        (new DefaultDatabase())->write($operation, $operation->statementInformation(), $session);

        self::assertSame('', $session->variables->database);
    }

    public function testReferencedResolvesAnImplicitTableBeforeLockClauseConditions(): void
    {
        $table = new ExplicitTable(new QualifiedName(new Name('t')));
        $query = new QueryStatement($table, [new LockingClause(LockStrength::Update), new LockingClause(LockStrength::Share)]);
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->text = 'unrelated invalid input';
        $node = new QueryRoot($query);
        $statement1 = (new Embedding())->statement($node);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $first = $result1;
        self::assertSame(1046, ($first instanceof SqlError ? [$first->getCode(), $first->sqlState(), $first->getMessage()] : null)[0] ?? null);
        $session->variables->database = 'd';
        $statement2 = (new Embedding())->statement($node);
        $parameters2 = array_fill(0, (new Bindings())->count($statement2), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation2 = (new Binding())->operation($session, $statement2, $parameters2, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
            $result2 = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2), $parameters2);
        } catch (SqlError $executionError) {
            $result2 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $second = $result2;
        self::assertSame(3569, ($second instanceof SqlError ? [$second->getCode(), $second->sqlState(), $second->getMessage()] : null)[0] ?? null);
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
        self::assertInstanceOf(ResultSet::class, $result);
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

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
    }

    public function testCheckLeavesStoredProgramMembersDeferred(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();

        self::assertInstanceOf(Completion::class, $session->query('CREATE PROCEDURE d.p() DELETE t FROM d.t')[0]);
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

    public function testBeforeAliasResolvesAnEarlierImplicitTableDatabase(): void
    {
        $session = (new Instance())->connect();
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('SELECT 1 FROM t AS repeated, t AS repeated');
    }

}
