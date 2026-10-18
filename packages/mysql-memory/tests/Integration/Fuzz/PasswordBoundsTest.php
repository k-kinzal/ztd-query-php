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
final class PasswordBoundsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     * @throws JsonException When the saved input corpus is invalid
     */
    public static function providerReleases(): iterable
    {
        /** @var list<string> $statements */
        $statements = json_decode((string) file_get_contents(__DIR__ . '/fixtures/password-bounds.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<string> $statements Complete password boundary and plugin histories
     */
    #[DataProvider('providerReleases')]
    public function testPluginPasswordBoundsPrecedeReplacementChecks(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);

                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPluginTransitions(): iterable
    {
        yield '5.7.44' => ['5.7.44'];
        yield '8.0.44' => ['8.0.44'];
    }

    #[DataProvider('providerPluginTransitions')]
    public function testTheSamePasswordValueFollowsTheAccountPlugin(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new \MySqlMemory\Instance($release))->connect();
            $statement = new \SqlSemantics\Platform\MySql\Statement\Account\SetPassword(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('plugin_user')), new \SqlSemantics\Platform\MySql\Statement\Account\Password(new \SqlSemantics\Platform\MySql\Statement\Literal\Text(str_repeat('a', 257))));
            $sql = (new \Fuzz\Structure\Reference())->sql($statement, $session->semantics()->profile());
            $pairs = array_map(static function (string $setup) use ($native, $session, $statement, $sql): array {
                $native->query($setup);
                $session->query($setup);
                $session->text = 'unrelated request';
                return [(new Native())->observe($native, $sql), (new \Fuzz\Structure\Audit\Memory())->observe($session, $statement)];
            }, ['DROP USER IF EXISTS plugin_user', "CREATE USER plugin_user IDENTIFIED WITH sha256_password BY 'old'", "ALTER USER plugin_user IDENTIFIED WITH mysql_native_password BY 'old'", "ALTER USER plugin_user IDENTIFIED WITH sha256_password BY 'old'"]);
            self::assertSame(array_map(serialize(...), array_column($pairs, 0)), array_map(serialize(...), array_column($pairs, 1)));
            self::assertSame([1133, 1396, null, 1396], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $pairs));
            self::assertSame('unrelated request', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }

}
