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
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\ParameterBindingCompiler;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(PdoPreparedExecution::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(PdoParameterBinder::class)]
#[UsesClass(PdoParameterType::class)]
#[UsesClass(PdoStatement::class)]
final class PdoPreparedExecutionTest extends TestCase
{
    public function testParameterBinderRewritesAgainstCurrentShadowStateForEveryPreparation(): void
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

    public function testUsesThePlatformParameterBindingCompilerResult(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $rewriter = static::createStub(SqlRewriter::class);
        $rewriter->method('rewrite')->willReturn(new RewritePlan('SELECT :original AS value', QueryKind::READ));
        $compiler = static::createMock(ParameterBindingCompiler::class);
        $compiler->expects(self::once())
            ->method('compile')
            ->with('SELECT :original AS value', ['original' => 11])
            ->willReturn(['sql' => 'SELECT :compiled AS value', 'params' => ['compiled' => 11]]);
        $session = new Session(
            $rewriter,
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class),
            parameterBindingCompiler: $compiler,
        );

        $prepared = (new PdoPreparedExecution($pdo, $session, 'SELECT :original AS value', []))
            ->prepare(['original' => 11]);

        self::assertSame('SELECT :compiled AS value', $prepared['statement']->queryString);
        self::assertSame(['compiled' => 11], $prepared['params']);
    }

    public function testFallsBackWhenTheSessionHasNoParameterBindingCompiler(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $rewriter = static::createStub(SqlRewriter::class);
        $rewriter->method('rewrite')->willReturn(new RewritePlan('SELECT ? AS value', QueryKind::READ));
        $session = new Session(
            $rewriter,
            new ShadowStore(),
            new ResultSelectRunner(),
            ZtdConfig::default(),
            static::createStub(ConnectionInterface::class),
        );

        $prepared = (new PdoPreparedExecution($pdo, $session, 'SELECT ? AS value', []))->prepare([12]);

        self::assertSame('SELECT ? AS value', $prepared['statement']->queryString);
        self::assertSame([12], $prepared['params']);
    }
}
