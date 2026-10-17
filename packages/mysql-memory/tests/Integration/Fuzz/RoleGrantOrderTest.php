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
final class RoleGrantOrderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE USER u, v, \'\'',
            'CREATE ROLE r, s',
            'CREATE USER fresh',
            'GRANT r TO fresh, missing_user',
            'SHOW GRANTS FOR fresh',
            'GRANT r, missing_role TO fresh',
            'SHOW GRANTS FOR fresh',
            'GRANT missing_role TO missing_user',
            'GRANT missing_role TO u',
            'GRANT missing_role TO \'\'',
            'GRANT missing_role TO u, \'\'',
            'GRANT r TO missing_user',
            'GRANT r TO u',
            'GRANT r TO \'\'',
            'GRANT r TO u, \'\'',
            'GRANT r, missing_role TO missing_user',
            'GRANT r, missing_role TO u',
            'GRANT r, missing_role TO \'\'',
            'GRANT r, missing_role TO u, \'\'',
            'GRANT missing_role, r TO missing_user',
            'GRANT missing_role, r TO u',
            'GRANT missing_role, r TO \'\'',
            'GRANT missing_role, r TO u, \'\'',
            'GRANT \'\' TO missing_user',
            'GRANT \'\' TO u',
            'GRANT \'\' TO \'\'',
            'GRANT \'\' TO u, \'\'',
            'GRANT \'\', missing_role TO missing_user',
            'GRANT \'\', missing_role TO u',
            'GRANT \'\', missing_role TO \'\'',
            'GRANT \'\', missing_role TO u, \'\'',
            'GRANT missing_role, \'\' TO missing_user',
            'GRANT missing_role, \'\' TO u',
            'GRANT missing_role, \'\' TO \'\'',
            'GRANT missing_role, \'\' TO u, \'\'',
            'GRANT `name` , `\'"``éé 猫ZéZé``éZ````\\""é`` Za``00` TO CURRENT_USER ( ) , \'a\'\'b\' , CURRENT_USER , CURRENT_USER ( ) WITH ADMIN OPTION',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Role and recipient lookup ordering, anonymous accounts and rollback
     */
    #[DataProvider('providerReleases')]
    public function testRoleGrantLookupsAndRollbackMatchNative(string $release, array $statements): void
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
