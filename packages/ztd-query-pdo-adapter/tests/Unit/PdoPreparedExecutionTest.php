<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoConnection;
use ZtdQuery\Adapter\Pdo\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\PdoParameterKind;
use ZtdQuery\Adapter\Pdo\PdoPreparedExecution;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
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
#[UsesClass(PdoParameterKind::class)]
#[UsesClass(PdoStatement::class)]
#[UsesClass(ZtdPdoException::class)]
final class PdoPreparedExecutionTest extends TestCase
{
    public function testPrepareRewritesAgainstTheShadowAsItStandsAtEachPreparation(): void
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

    public function testPrepareUsesWhatThePlatformsParameterCompilerAnswered(): void
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

    public function testPrepareUsesThePlanAsItStandsWhereTheDialectCompilesNoParameters(): void
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
    public function testItRefusesADriverOptionPdoCannotBeGiven(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $this->expectException(ZtdPdoException::class);
        $this->expectExceptionMessage('Driver option "cursor" must be a PDO attribute set to a bool, int or string, int given.');

        new PdoPreparedExecution($pdo, $session, 'SELECT 1', ['cursor' => 1]);
    }

    public function testParameterBinderAnswersWhatBindsTheCallersParameters(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $binder = new PdoParameterBinder();

        $execution = new PdoPreparedExecution($pdo, $session, 'SELECT 1', [], $binder);

        self::assertSame($binder, $execution->parameterBinder());
    }
}
