<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\RecordingParameterBindingCompiler;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\Driver\PdoParameterBinder;
use ZtdQuery\Adapter\Pdo\Driver\PdoParameterKind;
use ZtdQuery\Adapter\Pdo\Driver\PdoPreparedExecution;
use ZtdQuery\Adapter\Pdo\Driver\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
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
        $compiler = new RecordingParameterBindingCompiler(
            ['sql' => 'SELECT :compiled AS value', 'params' => ['compiled' => 11]],
        );
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
        self::assertSame([['SELECT :original AS value', ['original' => 11]]], $compiler->calls);
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
    public function testItRefusesADriverOptionThatIsNotAPdoAttribute(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $this->expectExceptionMessage('Driver option "cursor" must be a PDO attribute set to a bool, int or string, int given.');

        new PdoPreparedExecution($pdo, $session, 'SELECT 1', ['cursor' => 1]);
    }

    public function testItRefusesADriverOptionSetToSomethingPdoCannotBeGiven(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $this->expectExceptionMessage('Driver option #' . PDO::ATTR_CURSOR . ' must be a PDO attribute set to a bool, int or string, float given.');

        new PdoPreparedExecution($pdo, $session, 'SELECT 1', [PDO::ATTR_CURSOR => 1.5]);
    }

    #[DataProvider('providerDriverOptionPdoAccepts')]
    public function testItTakesADriverOptionPdoCanBeGiven(bool|int|string $value): void
    {
        $pdo = new PDO('sqlite::memory:');
        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $binder = new PdoParameterBinder();

        $execution = new PdoPreparedExecution($pdo, $session, 'SELECT 1', [PDO::ATTR_CURSOR => $value], $binder);

        self::assertSame($binder, $execution->parameterBinder());
    }

    /**
     * @return iterable<string, array{bool|int|string}>
     */
    public static function providerDriverOptionPdoAccepts(): iterable
    {
        yield 'a flag' => [true];
        yield 'a number' => [PDO::CURSOR_FWDONLY];
        yield 'a word' => ['forward'];
    }
}
