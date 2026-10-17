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
final class EncodingAssignmentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['utf8', 'utf8mb3', 'latin1', 'ucs2'] as $charset) {
            array_push($statements, 'SET NAMES ' . $charset, 'SET CHARACTER SET ' . $charset, 'SET NAMES ' . $charset . ' COLLATE missing_collation', 'SET NAMES ' . $charset . ' COLLATE latin1_bin');
            foreach (['character_set_client', 'character_set_connection', 'character_set_results', 'character_set_server', 'character_set_database'] as $variable) {
                $statements[] = 'SET ' . $variable . "='" . $charset . "'";
            }
        }
        foreach (['utf8_general_ci', 'utf8mb3_bin', 'ucs2_general_ci', 'latin1_bin'] as $collation) {
            foreach (['collation_connection', 'collation_server', 'collation_database'] as $variable) {
                $statements[] = 'SET ' . $variable . "='" . $collation . "'";
            }
        }
        array_push($statements, 'SET NAMES DEFAULT', 'SET CHARACTER SET DEFAULT', "SET @cs='utf8'", 'SET character_set_client=@cs', 'SET character_set_connection=@cs', 'SET collation_server=33', 'SET character_set_results=33');
        foreach (['5.6.51', '5.7.44', '8.4.7'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Encoding assignments through explicit names, evaluated variables and numeric ids
     */
    #[DataProvider('providerStatements')]
    public function testEncodingAssignmentsRetainNoticeOrderAndResultEncoding(string $release, array $statements): void
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
