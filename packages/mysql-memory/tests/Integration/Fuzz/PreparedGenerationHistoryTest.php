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
final class PreparedGenerationHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws JsonException When a saved native history is invalid
     */
    public static function providerReleases(): iterable
    {
        /** @var array<string, list<string>> $histories */
        $histories = json_decode((string) file_get_contents(__DIR__ . '/fixtures/prepared-generation-histories.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($histories as $name => $statements) {
            yield $name => ['5.7.44', $statements];
        }
    }

    /**
     * @param list<string> $statements Complete prepared generation histories, including observed native disconnects
     */
    #[DataProvider('providerReleases')]
    public function testPreparedGenerationFollowsTheEntireDestinationHistory(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new \Fuzz\Target\TemporaryFiles())->comparable((new Native())->observe($native, $sql), $release)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new \Fuzz\Target\TemporaryFiles())->comparable((new Native())->observe($memory, $sql), $release)), $statements);

                self::assertSame($expected, $actual, implode("\n", $statements));
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
