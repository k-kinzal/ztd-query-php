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
final class AccountAttributeStopTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     * @throws JsonException When the saved input corpus is invalid
     */
    public static function providerReleases(): iterable
    {
        /** @var list<string> $statements */
        $statements = json_decode((string) file_get_contents(__DIR__ . '/fixtures/account-attribute-stop.json'), true, 512, JSON_THROW_ON_ERROR);
        $statements[] = (string) file_get_contents(__DIR__ . '/fixtures/account-attribute-stop.sql');
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Account recipients before and after an invalid shared attribute
     */
    #[DataProvider('providerReleases')]
    public function testInvalidAttributesStopLaterRecipientsWithoutLosingEarlierErrors(string $release, array $statements, bool $prepared): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $server = Server::start($release);
            try {
                $observe = static function (string $sql, string $host, int $port, string $user, string $password) use ($prepared): string {
                    $connection = new mysqli($host, $user, $password, port: $port);
                    try {
                        return serialize((new Native())->observe($connection, $sql, prepared: $prepared));
                    } finally {
                        $connection->close();
                    }
                };
                $expected = array_map(static fn (string $sql): string => $observe($sql, $endpoint->host, $endpoint->port, $endpoint->username, $endpoint->password), $statements);
                $actual = array_map(static fn (string $sql): string => $observe($sql, $server->host, $server->port, 'root', ''), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
}
