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
final class RenameUserGrantTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $sql = ['SET NAMES utf8mb4', 'CREATE DATABASE d', 'CREATE DATABASE e', 'CREATE TABLE d.t (v INT,w INT)', 'CREATE TABLE d.u (v INT)', 'CREATE PROCEDURE d.p() SELECT 1', 'CREATE PROCEDURE d.q() SELECT 2'];
        $levels = [[], ['SELECT ON *.*'], ['SELECT ON d.*'], ['SELECT ON d.t'], ['SELECT(v) ON d.t'], ['EXECUTE ON PROCEDURE d.p'], ['PROXY ON root'], ['SELECT ON d.*', 'SELECT(v) ON d.t', 'EXECUTE ON PROCEDURE d.p', 'PROXY ON root'], ['SELECT ON d.*', 'SELECT ON e.*'], ['SELECT(v,w) ON d.t', 'SELECT(v) ON d.u'], ['EXECUTE ON PROCEDURE d.p', 'EXECUTE ON PROCEDURE d.q'], ['BACKUP_ADMIN,CONNECTION_ADMIN ON *.*']];
        foreach ($levels as $index => $grants) {
            foreach ([['%', '犬'], ['猫', '%'], ['猫', '犬']] as $case => [$from, $to]) {
                $a = "'s{$index}_{$case}'@'{$from}'";
                $b = "'t{$index}_{$case}'@'{$to}'";
                $sql[] = 'CREATE USER ' . $a;
                foreach ($grants as $grant) {
                    $sql[] = "GRANT $grant TO $a";
                }
                array_push($sql, "RENAME USER $a TO $b", "SHOW GRANTS FOR $b", "DROP USER IF EXISTS $a, $b");
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $sql];
        }
    }

    /**
     * @param list<string> $statements Global, database, table, column, routine and proxy grants followed through renames
     */
    #[DataProvider('providerHistories')]
    public function testRenamePreservesGrantScopesAndOrderedConversions(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query("DELETE FROM mysql.proxies_priv WHERE User='root' AND Host='%'");
            $native->query('FLUSH PRIVILEGES');
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
