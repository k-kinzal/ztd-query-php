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
final class ModelDomainsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = ['ALTER USER model_user DROP 0 FACTOR', 'ALTER USER model_user DROP 2 FACTOR', 'ALTER USER model_user DROP 1.5 FACTOR', 'ALTER USER model_user DROP 2e0 FACTOR', 'ALTER USER model_user DROP 0x2 FACTOR', 'ALTER USER model_user DROP 2147483647 FACTOR', 'ALTER USER model_user DROP 2147483648 FACTOR', 'ALTER USER model_user DROP 0002 FACTOR', 'REVOKE ALL, GRANT OPTION FROM model_user IDENTIFIED BY \'pw\'', 'REVOKE IF EXISTS ALL, GRANT OPTION FROM model_user IDENTIFIED BY \'pw\'', 'REVOKE ALL, GRANT OPTION FROM model_user IDENTIFIED BY \'pw\' IGNORE UNKNOWN USER', 'REVOKE IF EXISTS SELECT ON *.* FROM model_user IDENTIFIED BY \'pw\'', 'LOAD DATA INFILE 0x2 INTO TABLE model_table', 'LOAD DATA INFILE \'f\' INTO TABLE model_table IGNORE 1.5 LINES', 'LOAD DATA INFILE \'f\' COUNT 1.5 INTO TABLE model_table', 'LOAD DATA INFILE \'f\' INTO TABLE model_table PARALLEL = 1.5', 'LOAD DATA INFILE \'f\' INTO TABLE model_table COMPRESSION = 0x2'];
        foreach (["BY 'pw'", "BY PASSWORD 'hash'", 'WITH plugin', "WITH plugin AS 'hash'"] as $clause) {
            foreach (['ALL, GRANT OPTION', 'SELECT ON *.*', 'PROXY ON a'] as $target) {
                $statements[] = 'REVOKE ' . $target . ' FROM b IDENTIFIED ' . $clause;
            }
        }
        foreach (['d.t IGNORE 1.5 LINES', 't. IGNORE 1.5 LINES', 't.*', 't PARTITION (', 't PARTITION (p) (', 't ('] as $suffix) {
            $statements[] = "LOAD DATA INFILE 'f' INTO TABLE " . $suffix;
        }
        array_push(
            $statements,
            "CLONE LOCAL DATA DIRECTORY b'10'",
            "SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS(a INT COLLATE DEFAULT PATH '$')) AS j",
            'CREATE TEMPORARY TEMPORARY TABLE t (a INT) SECONDARY_ENGINE NULL',
            "CREATE USER u IDENTIFIED BY 'pw' AND IDENTIFIED BY PASSWORD 'hash'",
            'GRANT PROXY ON a TO b IDENTIFIED BY RANDOM PASSWORD',
            'GRANT SYSTEM_USER ON *.* TO b WITH GRANT OPTION GRANT OPTION',
            "SET PASSWORD = 'pw', @@time_zone = SYSTEM",
            'CHANGE MASTER TO MASTER_LOG_POS = 0xb',
            'CHANGE REPLICATION SOURCE TO SOURCE_LOG_POS = 0xb',
            "CREATE USER domain_password_user IDENTIFIED BY 'before'",
            'SET @before=9, @after=9',
            "SET @before=1, PASSWORD FOR domain_password_user = PASSWORD('after'), @after=2",
            'SELECT @before, @after',
            "SET @before=3, PASSWORD FOR domain_password_user = 'bad-hash', @after=4",
            'SELECT @before, @after',
        );
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements The ordered model-boundary regressions
     */
    #[DataProvider('providerReleases')]
    public function testLiteralDomainsMatchTheNativeServer(string $release, array $statements): void
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
