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
final class DeferredWindowPreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function providerReleases(): iterable
    {
        $statements = [
            'SELECT ROW(1,2)',
            'SELECT ROW(1,2) WINDOW w AS ()',
            'SELECT ROW(1,2) WINDOW w AS (ORDER BY missing)',
            'SELECT ROW(1,2) WHERE missing WINDOW w AS ()',
            'SELECT RANK() OVER w, ROW(1,2)',
            'SELECT RANK() OVER w, ROW(1,2) WINDOW w AS ()',
            'SELECT RANK() OVER w, ROW(1,2) WINDOW w AS (ORDER BY missing)',
            'SELECT RANK() OVER w, ROW(1,2) WHERE missing WINDOW w AS ()',
            'SELECT FIRST_VALUE(ROW(1,2)) OVER w',
            'SELECT FIRST_VALUE(ROW(1,2)) OVER w WINDOW w AS ()',
            'SELECT FIRST_VALUE(ROW(1,2)) OVER w WINDOW w AS (ORDER BY missing)',
            'SELECT FIRST_VALUE(ROW(1,2)) OVER w WHERE missing WINDOW w AS ()',
            'SELECT FIRST_VALUE(1) IGNORE NULLS OVER w',
            'SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS ()',
            'SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS (ORDER BY missing)',
            'SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WHERE missing WINDOW w AS ()',
            'SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w',
            'SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS ()',
            'SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS (ORDER BY missing)',
            'SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WHERE missing WINDOW w AS ()',
            'WITH c AS (SELECT ROW(1,2)) SELECT 1',
            'WITH c AS (SELECT ROW(1,2) WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT ROW(1,2) WINDOW w AS (ORDER BY missing)) SELECT 1',
            'WITH c AS (SELECT ROW(1,2) WHERE missing WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2)) SELECT 1',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2) WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2) WINDOW w AS (ORDER BY missing)) SELECT 1',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2) WHERE missing WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w WINDOW w AS (ORDER BY missing)) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w WHERE missing WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS (ORDER BY missing)) SELECT 1',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WHERE missing WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w) SELECT 1',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS (ORDER BY missing)) SELECT 1',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WHERE missing WINDOW w AS ()) SELECT 1',
            'WITH c AS (SELECT ROW(1,2)) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(1,2) WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(1,2) WINDOW w AS (ORDER BY missing)) SELECT 1 FROM c',
            'WITH c AS (SELECT ROW(1,2) WHERE missing WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2)) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2) WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2) WINDOW w AS (ORDER BY missing)) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER w, ROW(1,2) WHERE missing WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w WINDOW w AS (ORDER BY missing)) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(ROW(1,2)) OVER w WHERE missing WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS (ORDER BY missing)) SELECT 1 FROM c',
            'WITH c AS (SELECT FIRST_VALUE(1) IGNORE NULLS OVER w WHERE missing WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS ()) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WINDOW w AS (ORDER BY missing)) SELECT 1 FROM c',
            'WITH c AS (SELECT RANK() OVER () + FIRST_VALUE(1) IGNORE NULLS OVER w WHERE missing WINDOW w AS ()) SELECT 1 FROM c',
        ];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . '-text' => [$release, $statements, false];
            yield $release . '-prepared' => [$release, $statements, true];
        }
    }

    /**
     * @param list<string> $statements Complete CTE definitions and reached read dependencies
     */
    #[DataProvider('providerReleases')]
    public function testDeferredSelectionsRetainWindowDefinitionsAndPreparationOrder(string $release, array $statements, bool $prepared): void
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
