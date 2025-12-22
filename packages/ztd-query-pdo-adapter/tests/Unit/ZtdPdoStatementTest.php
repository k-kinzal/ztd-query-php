<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoStatement;
use ZtdQuery\Adapter\Pdo\ZtdPdoStatement;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Session;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ZtdPdoStatement::class)]
#[UsesClass(PdoStatement::class)]
final class ZtdPdoStatementTest extends TestCase
{
    public function testExecuteDelegatesWhenNoPlan(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('INSERT INTO t VALUES (1)');
        self::assertNotFalse($inner);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($stmt->execute());
    }

    public function testExecuteReturnsFalseWhenShouldExecuteIsFalse(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);

        $plan = new RewritePlan('SELECT 1', QueryKind::SKIPPED);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $stmt = new ZtdPdoStatement($inner, $session, $plan);
        self::assertFalse($stmt->execute());
    }

    public function testExecuteDelegatesWhenNoPostProcessingNeeded(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);

        $plan = new RewritePlan('SELECT * FROM t', QueryKind::READ);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));
        $stmt = new ZtdPdoStatement($inner, $session, $plan);
        self::assertTrue($stmt->execute());
    }

    public function testBindValueDelegatesToInner(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, name TEXT)');
        $inner = $pdo->prepare('INSERT INTO t VALUES (:id, :name)');
        self::assertNotFalse($inner);

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($stmt->bindValue(1, 'test'));
    }

    public function testRowCountDelegatesToInnerWhenNoResult(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo->exec('INSERT INTO t VALUES (1)');
        $pdo->exec('INSERT INTO t VALUES (2)');

        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);
        $inner->execute();

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertSame(0, $stmt->rowCount());
    }

    public function testCloseCursorDelegatesToInner(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);
        $inner->execute();

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertTrue($stmt->closeCursor());
    }

    public function testColumnCountDelegatesToInner(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, name TEXT, value REAL)');
        $inner = $pdo->prepare('SELECT * FROM t');
        self::assertNotFalse($inner);
        $inner->execute();

        $session = new Session(static::createStub(SqlRewriter::class), new ShadowStore(), new ResultSelectRunner(), ZtdConfig::default(), static::createStub(ConnectionInterface::class));

        $stmt = new ZtdPdoStatement($inner, $session, null);
        self::assertSame(3, $stmt->columnCount());
    }
}
