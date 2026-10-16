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
final class BulkLoadDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $queries = ["LOAD DATA S3 'a' COUNT 00000 REPLACE INTO TABLE A ROWS IDENTIFIED BY '\\\\\\\\' (`'`, @b, @utf8mb4_0900_ai_ci) ALGORITHM = BULK"];
        foreach (['', '(a)', '(@v)', '(a,@v)', 'SET a=1', '(a) SET a=1', "FIELDS TERMINATED BY ';'", "ROWS IDENTIFIED BY 'x'", 'IGNORE 1 LINES', 'CHARACTER SET utf8mb4'] as $tail) {
            $queries[] = "LOAD DATA INFILE '/missing' INTO TABLE t " . $tail . ' ALGORITHM=BULK';
        }
        foreach (["LOAD XML INFILE '/missing'", "LOAD DATA LOCAL INFILE '/missing'", "LOAD DATA INFILE '/missing' IGNORE", "LOAD DATA INFILE '/missing' REPLACE", "LOAD DATA URL 'x'", "LOAD DATA S3 'x' COUNT 0"] as $prefix) {
            $queries[] = $prefix . ' INTO TABLE t (a) ALGORITHM=BULK';
        }
        $queries[] = "LOAD DATA INFILE '/missing' INTO TABLE t PARTITION(p) (a) ALGORITHM=BULK";
        foreach (['INFILE', 'URL', 'S3'] as $source) {
            foreach (['0', '000', '1', '2'] as $count) {
                $queries[] = 'LOAD DATA ' . $source . " '/missing' COUNT " . $count . ' INTO TABLE t';
            }
        }
        $statements = [];
        foreach ([[], ['CREATE DATABASE d', 'USE d'], ['CREATE TABLE t(a INT,b INT)', 'INSERT INTO t VALUES (1,2)']] as $setup) {
            array_push($statements, ...$setup, ...$queries);
        }
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements LOAD option conflicts before selection, before table creation and with populated rows
     */
    #[DataProvider('providerStatements')]
    public function testLoadColumnAndAssignmentConflictsPreserveReleaseAndStateConditions(string $release, array $statements): void
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
