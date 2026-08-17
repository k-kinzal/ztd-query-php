<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoConnection;
use ZtdQuery\Adapter\Pdo\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\PdoParameterType;
use ZtdQuery\Adapter\Pdo\PdoPreparedExecution;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

#[CoversClass(PdoPreparedExecution::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(PdoParameterBinder::class)]
#[UsesClass(PdoParameterType::class)]
#[UsesClass(PdoStatement::class)]
final class PdoPreparedExecutionTest extends TestCase
{
    public function testRewritesAgainstCurrentShadowStateForEveryPreparation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, value TEXT)');
        $session = (new SqliteSessionFactory())->create(new PdoConnection($pdo), ZtdConfig::default());
        $session->execStatement("INSERT INTO items VALUES (1, 'before')");
        $execution = new PdoPreparedExecution($pdo, $session, 'SELECT value FROM items WHERE id = ?', []);

        $before = $execution->prepare([1]);
        self::assertTrue($execution->parameterBinder()->execute($before['statement'], $before['params']));
        self::assertSame('before', $before['statement']->fetchColumn());

        $session->execStatement("UPDATE items SET value = 'after' WHERE id = 1");
        $after = $execution->prepare([1]);
        self::assertTrue($execution->parameterBinder()->execute($after['statement'], $after['params']));
        self::assertSame('after', $after['statement']->fetchColumn());
    }
}
