<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RoutineDatabaseStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $release === '5.6.51' ? 1305 : 1046];
        }
    }

    #[DataProvider('providerReleases')]
    public function testOneCallTracksItsImplicitDatabaseAndRoutineLifetime(string $release, int $initialError): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $session = (new Instance($release, [], ['d']))->connect();
            $call = new M\Call\FunctionCall(new Name('f'), []);
            $sql = (new Reference())->sql($call, $session->semantics()->profile());
            $history = array_map(static function (string $setup) use ($native, $session, $call, $sql): array {
                $native->query($setup);
                $session->query($setup);
                $session->text = 'Unrelated input that is not SQL';
                return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $call)];
            }, ['DO 0', 'USE d', 'CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 7', 'DROP FUNCTION f']);
            self::assertSame([$initialError, 1305, null, 1305], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
            self::assertSame(array_map(serialize(...), array_column($history, 0)), array_map(serialize(...), array_column($history, 1)));
            self::assertSame('Unrelated input that is not SQL', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
