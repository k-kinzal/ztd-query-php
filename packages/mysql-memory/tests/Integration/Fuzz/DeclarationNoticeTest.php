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
use RuntimeException;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DeclarationNoticeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws RuntimeException When the saved regression is missing
     */
    public static function providerInputs(): iterable
    {
        $statements = ['CREATE DATABASE d', 'USE d'];
        foreach (['a YEAR(4) ZEROFILL SIGNED ZEROFILL', 'a INT ZEROFILL SIGNED ZEROFILL', 'a YEAR(4)', 'a INT(4)', 'a INT ZEROFILL'] as $definition) {
            foreach (['PRIMARY KEY ((CAST(1 AT LOCAL AS SIGNED)))', "PRIMARY KEY ((CAST(1 AT TIME ZONE 'text' AS DATETIME)))", "PRIMARY KEY ((CAST(CAST(1 AT TIME ZONE 'text' AS DATETIME) AT LOCAL AS SIGNED)))", 'PRIMARY KEY ((CAST(1 AT LOCAL AS SIGNED))), b INT ZEROFILL', 'PRIMARY KEY ((CAST(1 AT LOCAL AS SIGNED))), b YEAR(4)'] as $body) {
                $statements[] = 'CREATE TABLE d.t(' . $definition . ',' . $body . ')';
            }
        }
        $input = file_get_contents(__DIR__ . '/fixtures/year-attributes-before-local-cast.sql');
        if ($input === false) {
            throw new RuntimeException('The saved unseeded input is required.');
        }
        $statements[] = trim($input);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Completed declaration notices, later cast refusals and the unseeded regression
     */
    #[DataProvider('providerInputs')]
    public function testCompletedTypeWarningsAndCastFailuresRetainTheirOrder(string $release, array $statements): void
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
