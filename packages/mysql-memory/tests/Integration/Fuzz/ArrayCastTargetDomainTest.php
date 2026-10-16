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
final class ArrayCastTargetDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            $inputs = [];
            $types = array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind $kind): string => $kind->value, \SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::cases());
            array_push($types, 'FLOAT(54)', 'FLOAT(4294967296)', 'TIME(7)', 'DATETIME(7)', 'CHAR(2)', 'BINARY(2)', 'NCHAR(2)', 'CHAR(2) CHARACTER SET utf8mb4');
            foreach ($types as $type) {
                $cast = 'CAST(NULL AS ' . $type . ' ARRAY)';
                array_push(
                    $inputs,
                    'SELECT ' . $cast,
                    'SELECT BINARY 1, ' . $cast . ', BINARY 2',
                    'SELECT ' . $cast . ', CAST(1 AS DATETIME(8)), BINARY 2',
                    'SELECT ' . $cast . ',?',
                    'SELECT ' . $cast . ', CAST(1 AS JSON ARRAY), BINARY 2,?',
                    'SELECT CAST(BINARY 1 AS ' . $type . ' ARRAY), BINARY 2 FROM absent_table',
                );
            }
            yield $release => [$release, $inputs];
        }
    }

    /**
     * @param list<string> $statements Every element kind, including competing precision, marker and missing-table conditions
     */
    #[DataProvider('providerStatements')]
    public function testArrayElementTypesAndDiagnosticOrderMatchNative(string $release, array $statements): void
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
