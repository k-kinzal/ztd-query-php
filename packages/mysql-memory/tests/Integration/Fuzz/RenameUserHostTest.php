<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RenameUserHostTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $sql = ['SET NAMES utf8mb4', "RENAME USER CURRENT_USER() TO CURRENT_USER(), missing@st_collect TO CURRENT_USER(), name@'猫abcdef' TO repair@'h'"];
        $index = 0;
        foreach (['%', '猫', '猫abcdef'] as $from) {
            foreach (['%', '犬', '犬abcdef'] as $to) {
                foreach ([false, true] as $source) {
                    foreach ([false, true] as $target) {
                        $a = "'a{$index}'@'{$from}'";
                        $b = "'b{$index}'@'{$to}'";
                        $index++;
                        if ($source) {
                            $sql[] = 'CREATE USER ' . $a;
                        }
                        if ($target) {
                            $sql[] = 'CREATE USER ' . $b;
                        }
                        array_push($sql, "RENAME USER $a TO $b", "SHOW CREATE USER $a", "SHOW CREATE USER $b", "DROP USER IF EXISTS $a, $b");
                    }
                }
            }
        }
        array_push($sql, "CREATE USER 'a'@'猫'", "RENAME USER 'a'@'é' TO 'c'@'ö'", "SHOW GRANTS FOR 'a'@'猫'", "SHOW GRANTS FOR 'a'@'é'", "SHOW GRANTS FOR 'c'@'ö'", "SELECT User,Host FROM mysql.user WHERE User IN ('a','c') ORDER BY User,Host", "RENAME USER 'a'@'猫' TO 'd'@'犬'", "SHOW GRANTS FOR 'd'@'犬'", 'FLUSH PRIVILEGES', "SHOW GRANTS FOR 'c'@'?'", "SHOW GRANTS FOR 'd'@'?'", "SELECT User,Host FROM mysql.user WHERE User IN ('a','c','d') ORDER BY User,Host");
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $sql];
        }
    }

    /**
     * @param list<string> $statements Missing, existing and renamed accounts, including cache refresh and lossy host aliases
     */
    #[DataProvider('providerHistories')]
    public function testRenamesKeepWarningsAndStoredNamesAcrossAccountChanges(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $memory = new mysqli($server->host, 'root', '', port: $server->port);
            $observed = array_map(static function (string $sql) use ($native, $memory): array {
                return [serialize((new Native())->observe($native, $sql)), serialize((new Native())->observe($memory, $sql))];
            }, $statements);

            self::assertSame(array_column($observed, 0), array_column($observed, 1));
            $native->close();
            $memory->close();
        } finally {
            $server->stop();
            $container->stop();
        }
    }
}
