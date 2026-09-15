<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Session\PreparedQuery;
use ZtdQuery\Adapter\Pdo\Session\StatementExecution;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Sqlite\SqlitePdoResultColumnTypeResolver;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

#[CoversClass(StatementExecution::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoException::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoStatement::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[UsesClass(PreparedQuery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class StatementExecutionTest extends TestCase
{
    public function testExecutePreparedRefreshesShadowReadsAndReplaysBindings(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, value TEXT)');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $query = new PreparedQuery($session, 'SELECT value FROM items WHERE id = ?', static fn (string $sql): PDOStatement|false => $native->prepare($sql));
        $plan = $query->rewrite();
        $execution = new StatementExecution($query->prepare($plan->sql()), $session, $plan, $query);
        $execution->bindings()->parameter(1, static fn (PDOStatement $statement): bool => $statement->bindValue(1, 1, PDO::PARAM_INT));
        $session->execStatement("INSERT INTO items VALUES (1, 'first')");
        self::assertTrue($execution->execute());
        self::assertSame([['value' => 'first']], $execution->fetchAll());
        $first = $execution->native();
        $session->execStatement("UPDATE items SET value = 'second' WHERE id = 1");
        self::assertTrue($execution->execute());
        self::assertNotSame($first, $execution->native());
        self::assertSame([['value' => 'second']], $execution->fetchAll());
        self::assertNull($execution->result());
        self::assertCount(1, $execution->resultColumns(new SqlitePdoResultColumnTypeResolver()));
    }

    public function testResultUpdatesShadowAndReturnsItsResult(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, value TEXT)');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $query = new PreparedQuery($session, 'INSERT INTO items VALUES (?, ?)', static fn (string $sql): PDOStatement|false => $native->prepare($sql));
        $plan = $query->rewrite();
        $execution = new StatementExecution($query->prepare($plan->sql()), $session, $plan, $query);
        self::assertTrue($execution->execute([1, 'value']));
        self::assertNotNull($execution->result());
        self::assertSame(1, $execution->result()->rowCount());
        $result1 = $native->query('SELECT COUNT(*) FROM items');
        self::assertNotFalse($result1);
        self::assertSame(0, $result1->fetchColumn());
        $shadow = $native->query($session->rewrite('SELECT COUNT(*) AS total FROM items')->sql());
        self::assertNotFalse($shadow);
        self::assertSame(1, $shadow->fetchColumn());
    }

    public function testNativeUnrewrittenExecutionDelegatesToTheNativeStatement(): void
    {
        $native = new PDO('sqlite::memory:');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $statement = $native->prepare('SELECT ? AS value');
        $execution = new StatementExecution($statement, $session, null);
        self::assertTrue($execution->execute([12]));
        self::assertSame($statement, $execution->native());
        self::assertSame([['value' => '12']], $execution->fetchAll());
        self::assertSame($statement->rowCount(), $execution->rowCount());
        self::assertNull($execution->result());
    }

    public function testExecuteSilentFailureDoesNotProduceAShadowResult(): void
    {
        $native = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
        $native->exec('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $native->exec('INSERT INTO items VALUES (1)');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $execution = new StatementExecution($native->prepare('INSERT INTO items VALUES (1)'), $session, null);
        self::assertFalse($execution->execute());
        self::assertNull($execution->result());
    }    public function testFetchAllReturnsAssociativeRows(): void
    {
        $native = new PDO('sqlite::memory:');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $statement = $native->prepare('SELECT 7 AS id, NULL AS name');
        self::assertNotFalse($statement);
        $execution = new StatementExecution($statement, $session, null);
        $execution->execute();
        self::assertSame([['id' => 7, 'name' => null]], $execution->fetchAll());
        self::assertSame([], $execution->fetchAll());
    }

    public function testResultColumnsRemainAvailableAfterRowsAreConsumed(): void
    {
        $native = new PDO('sqlite::memory:');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $statement = $native->prepare('SELECT 7 AS id, NULL AS name');
        self::assertNotFalse($statement);
        $execution = new StatementExecution($statement, $session, null);
        $execution->execute();
        $execution->fetchAll();
        $columns = $execution->resultColumns(new SqlitePdoResultColumnTypeResolver());
        self::assertSame(['id', 'name'], array_map(static fn ($column): string => $column->name, $columns));
    }

    public function testRowCountTracksNativeMutations(): void
    {
        $native = new PDO('sqlite::memory:');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $statement = $native->prepare('SELECT 7 AS id, NULL AS name');
        self::assertNotFalse($statement);
        $execution = new StatementExecution($statement, $session, null);
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $insert = $native->prepare('INSERT INTO users VALUES (1), (2)');
        self::assertNotFalse($insert);
        $write = new StatementExecution($insert, $session, null);
        self::assertTrue($write->execute());
        self::assertSame(2, $write->rowCount());
    }

    public function testBindingsRemainAvailableAcrossExecutions(): void
    {
        $native = new PDO('sqlite::memory:');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $statement = $native->prepare('SELECT 7 AS id, NULL AS name');
        self::assertNotFalse($statement);
        $execution = new StatementExecution($statement, $session, null);
        $bindings = $execution->bindings();
        $bindings->fetch(static fn (PDOStatement $target): bool => $target->setFetchMode(PDO::FETCH_NUM));
        $bindings->apply($execution->native());
        $execution->execute();
        self::assertSame([7, null], $execution->native()->fetch());
        self::assertSame($bindings, $execution->bindings());
    }

    public function testPostProcessAppliesResultSelectRowsToTheShadow(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $plan = $session->rewrite("INSERT INTO users VALUES (1, 'Ada')");
        $statement = $native->prepare($plan->sql());
        self::assertNotFalse($statement);
        $statement->execute();
        $execution = new StatementExecution($statement, $session, null);
        self::assertTrue($execution->postProcess($plan));
        self::assertNotNull($execution->result());
        self::assertSame(1, $execution->result()->rowCount());
        $read = $native->query($session->rewrite('SELECT name FROM users')->sql());
        self::assertNotFalse($read);
        self::assertSame('Ada', $read->fetchColumn());
    }


    public function testBindParameterRetainsReferencesAcrossRepreparation(): void
    {
        $native = new PDO('sqlite::memory:');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $statement = $native->prepare('SELECT :id AS id');
        self::assertNotFalse($statement);
        $prepared = new PreparedQuery($session, 'SELECT :id AS id', $native->prepare(...));
        $execution = new StatementExecution($statement, $session, null, $prepared);
        $id = 7;
        self::assertTrue($execution->bindParameter(':id', static function (PDOStatement $target) use (&$id): bool {
            return $target->bindParam(':id', $id, PDO::PARAM_INT);
        }));
        self::assertTrue($execution->execute());
        self::assertSame(7, $execution->native()->fetchColumn());
        $id = 9;
        self::assertTrue($execution->execute());
        self::assertSame(9, $execution->native()->fetchColumn());
    }
}
