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
final class MissingNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [];
        foreach (['TABLE', 'VIEW'] as $kind) {
            foreach (['', 'IF EXISTS '] as $option) {
                foreach (['a', 'é', '猫'] as $letter) {
                    $name = str_repeat($letter, 50);
                    $statements[] = 'DROP ' . $kind . ' ' . $option . 'absent.`' . $name . '0`, absent.`' . $name . '1`, absent.`' . $name . '2`';
                }
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $history = $statements;
            if (version_compare($release, '5.7', '>=')) {
                $word = version_compare($release, '8.0', '>=') ? 'REPLICA' : 'SLAVE';
                foreach (['a', 'é', '猫'] as $letter) {
                    $channel = "'\n" . str_repeat($letter, 150) . "'";
                    $history[] = 'SHOW ' . $word . ' STATUS FOR CHANNEL ' . $channel;
                    $history[] = 'START ' . $word . ' FOR CHANNEL ' . $channel;
                }
            }
            yield $release => [$release, $history];
        }
    }

    /**
     * @param list<string> $statements The ordered long-name regressions
     */
    #[DataProvider('providerReleases')]
    public function testUnknownNameMessagesMatchTheNativeServer(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new PDO('mysql:host=' . $endpoint->host . ';port=' . $endpoint->port . ';charset=utf8mb4', $endpoint->username, $endpoint->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $server = Server::start($release);
            try {
                $memory = new PDO($server->dsn() . ';charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
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
