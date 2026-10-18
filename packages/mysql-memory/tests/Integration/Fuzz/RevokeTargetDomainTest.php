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
final class RevokeTargetDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     * @throws JsonException When the saved input corpus is invalid
     */
    public static function providerReleases(): iterable
    {
        /** @var list<string> $statements */
        $statements = json_decode((string) file_get_contents(__DIR__ . '/fixtures/revoke-target-domain.json'), true, 512, JSON_THROW_ON_ERROR);
        /** @var list<string> $flags */
        $flags = json_decode((string) file_get_contents(__DIR__ . '/fixtures/revoke-flags-domain.json'), true, 512, JSON_THROW_ON_ERROR);
        /** @var list<string> $prepared */
        $prepared = json_decode((string) file_get_contents(__DIR__ . '/fixtures/revoke-prepared-domain.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-sql-prepared' => [$release, $prepared, false];
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
        yield '8.4.7-flags' => ['8.4.7', $flags, false];
    }

    /**
     * @param list<string> $statements Complete revocations, stored definitions and transaction histories
     */
    #[DataProvider('providerReleases')]
    public function testRevocationsPreserveDefinitionInvocationAndTransactionPhases(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared && preg_match('/^REVOKE /', $sql) === 1)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared && preg_match('/^REVOKE /', $sql) === 1)), $statements);

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
