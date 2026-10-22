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
final class HandlerKeyDependenciesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     * @throws JsonException When the saved input corpus is invalid
     */
    public static function providerReleases(): iterable
    {
        /** @var list<string> $statements */
        $statements = json_decode((string) file_get_contents(__DIR__ . '/fixtures/handler-key-dependencies.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            /** @var list<string> $modern */
            $modern = in_array($release, ['5.6.51', '5.7.44'], true) ? [] : json_decode((string) file_get_contents(__DIR__ . '/fixtures/handler-json-keys.json'), true, 512, JSON_THROW_ON_ERROR);
            yield $release . '-text' => [$release, [...$statements, ...$modern], false];
            yield $release . '-prepared' => [$release, [...$statements, ...$modern], true];
        }
    }

    /**
     * @param list<string> $statements Key dependencies, versioned session reads and preparation notices
     */
    #[DataProvider('providerReleases')]
    public function testKeyNamesResolveBeforeSeekEligibilityAndKeepPrecedingNotices(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql, prepared: $prepared && str_starts_with($sql, 'HANDLER'))), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql, prepared: $prepared && str_starts_with($sql, 'HANDLER'))), $statements);

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
