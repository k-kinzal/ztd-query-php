<?php

declare(strict_types=1);

namespace Tests\Integration;

use Container\Endpoint;
use Container\MySqlRelease;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Containers\ReuseMode;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class NativeViewNamespaceTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testStoredViewUsesPersistentTablesBehindTheSessionShadow(string $version): void
    {
        $container = Testcontainers::run((new (MySqlRelease::container($version))())->withReuseMode(ReuseMode::ADD()));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $connection = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $administrator = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $connection->exec('CREATE DATABASE view_namespace');
            $connection->exec('USE view_namespace');
            $connection->exec('CREATE TABLE t(a VARCHAR(16))');
            $connection->exec("INSERT INTO t VALUES ('persistent')");
            $connection->exec('CREATE VIEW v AS SELECT a FROM t');
            $connection->exec('CREATE TEMPORARY TABLE t(a VARCHAR(16))');
            $connection->exec("INSERT INTO t VALUES ('temporary')");
            $table = $connection->query('SELECT a FROM t');
            $view = $connection->query('SELECT a FROM v');
            self::assertInstanceOf(PDOStatement::class, $table);
            self::assertInstanceOf(PDOStatement::class, $view);
            self::assertSame(['temporary'], $table->fetchAll(PDO::FETCH_COLUMN));
            self::assertSame(['persistent'], $view->fetchAll(PDO::FETCH_COLUMN));

            $administrator->exec('DROP TABLE view_namespace.t');
            try {
                $connection->query('SELECT a FROM v');
                self::fail('A session shadow cannot replace a missing persistent view dependency.');
            } catch (PDOException $error) {
                self::assertSame(1356, $error->errorInfo[1] ?? null);
            }
        } finally {
            $table = null;
            $view = null;
            $connection = null;
            $administrator = null;
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (MySqlRelease::versions() as $version) {
            yield $version => [$version];
        }
    }
}
