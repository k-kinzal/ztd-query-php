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
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

#[CoversClass(PreparedQuery::class)]
#[UsesClass(ZtdPdoException::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdoStatement::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\ZtdPdo::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Driver\PdoStatement::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\StatementExecution::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\Bindings::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\BufferedRow::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\DriverSessionFactory::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterKind::class)]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\ParameterBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[UsesClass(\ZtdQuery\Adapter\Pdo\Session\ConnectionExecution::class)]
final class PreparedQueryTest extends TestCase
{
    public function testRewriteUsesTheCurrentShadowOnEveryExecution(): void
    {
        $native = new PDO('sqlite::memory:');
        $native->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, value TEXT)');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $query = new PreparedQuery($session, 'SELECT value FROM items WHERE id = ?', static fn (string $sql): PDOStatement|false => $native->prepare($sql));
        $session->execStatement("INSERT INTO items VALUES (1, 'before')");
        $before = $query->prepare($query->rewrite()->sql());
        self::assertTrue($before->execute([1]));
        self::assertSame('before', $before->fetchColumn());
        $session->execStatement("UPDATE items SET value = 'after' WHERE id = 1");
        $after = $query->prepare($query->rewrite()->sql());
        self::assertTrue($after->execute([1]));
        self::assertSame('after', $after->fetchColumn());
        $result1 = $native->query('SELECT COUNT(*) FROM items');
        self::assertNotFalse($result1);
        self::assertSame(0, $result1->fetchColumn());
    }

    public function testPrepareReportsASilentDriverFailure(): void
    {
        $native = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);
        $session = (new SqliteSessionFactory())->create(new PdoConnection($native), ZtdConfig::default());
        $query = new PreparedQuery($session, 'SELECT 1', static fn (string $sql): PDOStatement|false => $native->prepare($sql));
        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('PDO failed to prepare rewritten SQL.');
        $query->prepare('SELECT * FROM missing_table');
    }
}
