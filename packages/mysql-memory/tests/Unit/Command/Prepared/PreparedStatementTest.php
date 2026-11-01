<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PreparedStatement;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreparedStatement::class)]
#[Small]
final class PreparedStatementTest extends TestCase
{
    public function testExecuteInsertUsesValuesWithoutReadingItsLabel(): void
    {
        $session = (new Instance('8.4.7', databases: ['pbt', 'other']))->connect(database: 'pbt');
        $session->execute('CREATE TABLE t (a INT)');
        $statement = $session->analyze("INSERT t VALUE ('42')", true);
        $prepared = PreparedStatement::capture($statement, $session);
        $session->execute('USE other');
        $reply = $prepared->execute($session, 'not a SQL statement', []);
        self::assertSame($statement, $prepared->statement);
        self::assertSame($statement->statementInformation()->connection, $prepared->literals->connection);
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
        $table = $session->instance->dictionary->table('pbt', 't');
        self::assertNotNull($table);
        self::assertSame([[42]], array_values($table->data->rows));
        self::assertSame('other', $session->variables->database);
    }

    public function testExecuteRetainsAnExplicitEngineButUsesTheCurrentImplicitDefault(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; USE d; SET sql_mode=''; PREPARE a FROM 'CREATE TABLE explicit_engine(a INT) ENGINE=missing'; PREPARE b FROM 'CREATE TABLE implicit_engine(a INT)'; SET default_storage_engine=MEMORY; SET sql_mode='NO_ENGINE_SUBSTITUTION'");
        $session->query('EXECUTE a; EXECUTE b');
        $explicit = $session->instance->dictionary->table('d', 'explicit_engine');
        $implicit = $session->instance->dictionary->table('d', 'implicit_engine');

        self::assertNotNull($explicit);
        self::assertNotNull($implicit);
        self::assertSame('InnoDB', $explicit->definition->engine);
        self::assertSame('MEMORY', $implicit->definition->engine);
        self::assertSame([], $session->variables->preparedEngines);
        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testCaptureKeepsTheSameStructureAndDefinitionContext(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET NAMES utf8mb4; CREATE DATABASE d; USE d');
        $operation = $session->analyze('SELECT 1', true);

        $prepared = PreparedStatement::capture($operation, $session);

        self::assertSame($operation, $prepared->statement);
        self::assertSame($operation->profile(), $prepared->profile);
        self::assertSame('d', $prepared->database);
        self::assertSame('utf8mb4', $prepared->literals->client?->name);
    }

    public function testExecuteUsesTheStructureAndRestoresTheCallingContext(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET NAMES utf8mb4; CREATE DATABASE a; CREATE DATABASE b; USE a; CREATE TABLE `猫` (`猫` INT); INSERT INTO `猫` VALUES (7)');
        $operation = $session->analyze('SELECT `猫`, DATABASE(), CHARSET(\'猫\'), @@character_set_client FROM `猫`', true);
        $prepared = PreparedStatement::capture($operation, $session);
        $session->query('USE b; SET NAMES latin1');

        $result = $prepared->execute($session, 'unrelated invalid source', []);

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', 'a', 'utf8mb4', 'latin1']], $result->rows);
        self::assertSame('?', $result->columns[0]->name);
        self::assertSame('b', $session->variables->database);
        self::assertFalse($session->replayed);
        self::assertNull($session->preparation->resolution);
        self::assertSame('unrelated invalid source', $session->text);
    }

    public function testRunReturnsAResultWithoutParsingItsRequestLabel(): void
    {
        $session = (new Instance())->connect();
        $prepared = PreparedStatement::capture($session->analyze('SELECT 1', true), $session);

        $answers = $prepared->run($session, 'this is not SQL', []);

        self::assertCount(1, $answers);
        self::assertInstanceOf(ResultSet::class, $answers[0]);
        self::assertSame([['1']], $answers[0]->rows);
    }

    public function testRunRecordsAStateFailureAndRestoresTheDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE a; CREATE DATABASE b; USE a; CREATE TABLE t(a INT)');
        $prepared = PreparedStatement::capture($session->analyze('SELECT * FROM t', true), $session);
        $session->query('DROP TABLE t; USE b');

        $answers = $prepared->run($session, 'unrelated invalid source', []);

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(1146, $answers[0]->getCode());
        self::assertSame('b', $session->variables->database);
        self::assertSame(-1, $session->variables->rowCount);
        self::assertNull($session->preparation->resolution);
    }

    public function testRunRefusesAClosedSession(): void
    {
        $session = (new Instance())->connect();
        $prepared = PreparedStatement::capture($session->analyze('SELECT 1', true), $session);
        $session->close();

        $answers = $prepared->run($session, 'unrelated invalid source', []);

        self::assertInstanceOf(SqlError::class, $answers[0]);
        self::assertSame(2006, $answers[0]->getCode());
    }
    public function testRebindRefreshesTypesAndKeepsTheDefinitionDatabase(): void
    {
        $session = (new Instance('8.4.7', databases: ['original', 'caller']))->connect(database: 'original');
        $prepared = PreparedStatement::capture($session->analyze('SELECT ? AS value', true), $session);
        $session->use('caller');
        $replacement = $prepared->rebind($session, [[12, \MySqlMemory\Typing\Domain::integer()]]);
        $result = $replacement->execute($session, 'unrelated label', [[12, \MySqlMemory\Typing\Domain::integer()]]);
        self::assertNotSame($prepared->statement, $replacement->statement);
        self::assertSame('original', $replacement->database);
        self::assertSame('caller', $session->variables->database);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['12']], $result->rows);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::LongLong, $result->columns[0]->type);
    }

}
