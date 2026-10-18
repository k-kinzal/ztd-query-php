<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class InitialAuthenticationTest extends TestCase
{
    /**
     * @throws JsonException When the recorded inputs are malformed
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'CREATE USER CURRENT_USER IDENTIFIED WITH missing',
            'CREATE USER CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED BY \'p\'',
            'CREATE USER CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH SESSION AS X\'0f\'',
            'CREATE USER CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH mysql_native_password AS X\'0f\'',
            'CREATE USER CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH caching_sha2_password BY \'p\'',
            'CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing',
            'CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED BY \'p\'',
            'CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH SESSION AS X\'0f\'',
            'CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH mysql_native_password AS X\'0f\'',
            'CREATE USER IF NOT EXISTS CURRENT_USER IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH caching_sha2_password BY \'p\'',
            'CREATE USER \'root\'@\'%\' IDENTIFIED WITH missing',
            'CREATE USER \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED BY \'p\'',
            'CREATE USER \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH SESSION AS X\'0f\'',
            'CREATE USER \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH mysql_native_password AS X\'0f\'',
            'CREATE USER \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH caching_sha2_password BY \'p\'',
            'CREATE USER IF NOT EXISTS \'root\'@\'%\' IDENTIFIED WITH missing',
            'CREATE USER IF NOT EXISTS \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED BY \'p\'',
            'CREATE USER IF NOT EXISTS \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH SESSION AS X\'0f\'',
            'CREATE USER IF NOT EXISTS \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH mysql_native_password AS X\'0f\'',
            'CREATE USER IF NOT EXISTS \'root\'@\'%\' IDENTIFIED WITH missing INITIAL AUTHENTICATION IDENTIFIED WITH caching_sha2_password BY \'p\'',
        ];
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/create-user-initial-authentication.sql');
        /** @var list<string> $ordered */
        $ordered = json_decode((string) file_get_contents(__DIR__ . '/fixtures/initial-authentication-order.json'), true, flags: JSON_THROW_ON_ERROR);
        $statements = [...$statements, ...$ordered];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Initial credentials and existing-account observations
     */
    #[DataProvider('providerReleases')]
    public function testInitialAuthenticationPrecedesSkippingAnExistingAccount(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
