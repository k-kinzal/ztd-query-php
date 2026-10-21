<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LoadClientPdoTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-data' => [$release, 'DATA', "1,alpha\n2,beta\n", "FIELDS TERMINATED BY ','"];
            yield $release . '-xml' => [$release, 'XML', '<row a="1" b="alpha"/><row a="2"><b>beta</b></row>', ''];
        }
    }

    #[DataProvider('providerReleases')]
    public function testPdoUploadsRowsAndKeepsTheConnectionUsable(string $release, string $format, string $contents, string $layout): void
    {
        $directory = sys_get_temp_dir() . '/load-pdo-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $file = $directory . '/input';
        file_put_contents($file, $contents);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_LOCAL_INFILE_DIRECTORY => $directory];
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, $options);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', $options);
                $observer = new Observer();
                $statements = ['SET GLOBAL local_infile=1', 'CREATE DATABASE d', 'USE d', 'CREATE TABLE t(a INT PRIMARY KEY,b VARCHAR(40))', "LOAD $format LOCAL INFILE '$file' INTO TABLE t $layout (a,@b) SET b=@b", 'SELECT * FROM t ORDER BY a', 'SELECT 42'];
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
                $rows = $memory->query('SELECT * FROM t ORDER BY a');
                self::assertInstanceOf(PDOStatement::class, $rows);
                self::assertSame([[1, 'alpha'], [2, 'beta']], $rows->fetchAll(PDO::FETCH_NUM));
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
            unlink($file);
            rmdir($directory);
        }
    }
}
