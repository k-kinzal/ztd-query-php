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
final class AccountAuthenticationNoticeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     * @throws JsonException When the saved input corpus is invalid
     */
    public static function providerReleases(): iterable
    {
        /** @var list<string> $statements */
        $statements = json_decode((string) file_get_contents(__DIR__ . '/fixtures/account-authentication-notices.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Original fuzz input and authentication conditions for existing and absent accounts
     */
    #[DataProvider('providerReleases')]
    public function testAuthenticationStringsAreCheckedBeforeExistenceNotes(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && str_starts_with($sql, 'CREATE USER'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, parameters: $prepared && str_starts_with($sql, 'SELECT') ? substr_count($sql, '?') : 0, prepared: $prepared && str_starts_with($sql, 'CREATE USER'))), $statements);

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
