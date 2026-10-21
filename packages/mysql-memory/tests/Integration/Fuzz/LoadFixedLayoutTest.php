<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Target\DisposableMySql;
use Fuzz\Target\Observer;
use MySqlMemory\Server\Server;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LoadFixedLayoutTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $inputs = [];
        foreach (['', 'LOCAL '] as $local) {
            foreach (['(a,@v)', '(absent,@v)', '(a,@v) SET b=ROW(1,2)', '(a,@v) SET absent=1', '(a,@v) SET b=@@missing', '(a,@v) SET b=(SELECT b FROM absent)'] as $assignment) {
                $inputs[] = "LOAD DATA {$local}INFILE 'f' INTO TABLE t FIELDS TERMINATED BY '' ENCLOSED BY '' $assignment";
                $inputs[] = "LOAD DATA {$local}INFILE 'f' INTO TABLE t PARTITION(p) FIELDS TERMINATED BY '' ENCLOSED BY '' $assignment";
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $release) {
            yield $release => [$release, ['SET GLOBAL local_infile=1', ...$inputs, 'CREATE DATABASE d', 'USE d', ...$inputs, 'CREATE TABLE t(a INT,b VARCHAR(40))', ...$inputs, 'DROP TABLE t', 'CREATE TABLE t(a INT,b VARCHAR(40)) PARTITION BY RANGE(a) (PARTITION other VALUES LESS THAN MAXVALUE)', ...$inputs, 'SET GLOBAL local_infile=0', ...$inputs]];
        }
    }

    /**
     * @param list<string> $statements Identical fixed-row requests across database, table and LOCAL states
     */
    #[DataProvider('providerStatements')]
    public function testRetainedInputRolesPreserveTheNativePreparationOrder(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port, $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $observer = new Observer();
                $expected = array_map(static fn (string $sql): string => serialize($observer->observe($native, $sql, true)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize($observer->observe($memory, $sql, true)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
