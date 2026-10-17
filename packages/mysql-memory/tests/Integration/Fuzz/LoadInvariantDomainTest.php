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
final class LoadInvariantDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $queries = [
            'LOAD DATA S3 \'x\' INTO TABLE t (a) ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE missing.t (a) ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE t CHARACTER SET no_such_charset (a) ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE t CHARACTER SET utf8 (a) ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE t SET a=@@no_such_variable ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE t SET a=missing() ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE t SET a=(SELECT no_such_column FROM missing_table) ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE t (a)',
            'LOAD DATA S3 \'x\' COUNT 0 INTO TABLE t (a)',
            'LOAD XML S3 \'x\' INTO TABLE t SET a=@@no_such_variable ALGORITHM=BULK',
            'LOAD DATA S3 \'x\' INTO TABLE t FIELDS ENCLOSED BY \'xx\' (a) ALGORITHM=BULK',
            'LOAD DATA INFILE \'x\' INTO TABLE t (a) PARALLEL=1 ALGORITHM=BULK',
            'LOAD DATA INFILE \'x\' INTO TABLE t SET a=1 MEMORY=1 ALGORITHM=BULK',
            'LOAD DATA INFILE \'x\' COUNT 0 INTO TABLE t COMPRESSION=\'ZSTD\' ALGORITHM=BULK',
            'CREATE PROCEDURE p() LOAD DATA S3 \'x\' INTO TABLE t (a) ALGORITHM=BULK',
            'CREATE PROCEDURE p() BEGIN IF FALSE THEN LOAD DATA S3 \'x\' INTO TABLE t (a) ALGORITHM=BULK; END IF; END',
        ];
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
    public function testRefusedLoadStructuresRetainNativeInputAndProgramPrecedence(string $release, array $statements): void
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
