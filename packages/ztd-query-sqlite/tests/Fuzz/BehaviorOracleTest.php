<?php

declare(strict_types=1);

namespace Tests\Fuzz;

use Fuzz\Shared\Database\Connection;
use Fuzz\Shared\Database\Sandbox;
use Fuzz\Shared\Database\SessionExecutor;
use Fuzz\Shared\Input\Command;
use Fuzz\Shared\Oracle\BehaviorTarget;
use Fuzz\Shared\Oracle\Comparison;
use Fuzz\Shared\Oracle\Finding;
use Fuzz\Shared\Oracle\Rejection;
use Fuzz\Shared\Oracle\State;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;
use ZtdQuery\Session;

/**
 * Verify that corrupted outcomes cannot satisfy the shared database oracle.
 */
#[CoversNothing]
#[Medium]
final class BehaviorOracleTest extends TestCase
{
    /**
     * Errors are only accepted for the exact declared negative case.
     */
    public function testUnrelatedErrorFails(): void
    {
        $this->expectException(Finding::class);
        Rejection::verify(new PDOException('syntax error'), 'unique');
    }

    /**
     * An invalid statement succeeding is a finding.
     */
    public function testSwallowedErrorFails(): void
    {
        $this->expectException(Finding::class);
        Rejection::verify(null, 'unique');
    }

    /**
     * Equality retains duplicate multiplicity and column names.
     */
    public function testMissingDuplicateFails(): void
    {
        $this->expectException(Finding::class);
        Comparison::same(Comparison::rows([['id' => 1], ['id' => 1]]), Comparison::rows([['id' => 1]]), 'Result multiplicity');
    }

    /**
     * Compare the row state even if the generated query returned nothing.
     */
    public function testLostVirtualWriteFails(): void
    {
        $native = new Sandbox('sqlite');
        $physical = new Sandbox('sqlite');
        $native->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $physical->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $native->pdo->exec('INSERT INTO items VALUES (1)');
        $connection = new Connection($physical->pdo);
        $session = (new SqliteSessionFactory())->create($connection, ZtdConfig::default());
        $this->expectException(Finding::class);
        State::verify($native, new SessionExecutor($session, $connection));
    }

    /**
     * Empty tables still require identical columns.
     */
    public function testEmptySchemaMismatchFails(): void
    {
        $native = new Sandbox('sqlite');
        $physical = new Sandbox('sqlite');
        $native->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, missing TEXT)');
        $physical->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY)');
        $connection = new Connection($physical->pdo);
        $session = (new SqliteSessionFactory())->create($connection, ZtdConfig::default());
        $this->expectException(Finding::class);
        $this->expectExceptionMessage('Virtual columns differ');
        State::verify($native, new SessionExecutor($session, $connection));
    }

    /**
     * The positive path may not accept an invalid generator statement even if both sides fail.
     */
    public function testBothSidesFailingIsStillAFinding(): void
    {
        $native = new Sandbox('sqlite');
        $physical = new Sandbox('sqlite');
        $factory = new SqliteSessionFactory();
        $connection = new Connection($physical->pdo);
        $session = $factory->create($connection, ZtdConfig::default());
        $this->expectException(Finding::class);
        (new BehaviorTarget($factory, $native, $physical))->step(new SessionExecutor($session, $connection), new Command('SELECT * FROM missing_table'));
    }

    /**
     * The complete target detects physical changes even when query results match.
     */
    public function testPhysicalWriteFailsEvenWithMatchingResults(): void
    {
        $native = new Sandbox('sqlite');
        $physical = new Sandbox('sqlite');
        $factory = self::createMock(SessionFactory::class);
        $factory->expects(self::once())->method('create')->willReturnCallback(
            static function (ConnectionInterface $connection, ZtdConfig $config): Session {
                $connection->query('DELETE FROM fuzz_0');
                return (new SqliteSessionFactory())->create($connection, $config);
            }
        );
        $this->expectException(Finding::class);
        $this->expectExceptionMessage('Physical database changed');
        (new BehaviorTarget($factory, $native, $physical))(str_repeat("\0", 20));
    }

    /**
     * Matching valid executions retain the distinct physical sentinel.
     */
    public function testValidExecutionPreservesPhysicalSentinel(): void
    {
        $native = new Sandbox('sqlite');
        $physical = new Sandbox('sqlite');
        (new BehaviorTarget(new SqliteSessionFactory(), $native, $physical))(str_repeat("\0", 20));
        self::assertSame([], $native->catalog->query('SELECT id FROM fuzz_0'));
        self::assertSame([['id' => 9000]], $physical->catalog->query('SELECT id FROM fuzz_0'));
    }
}
