<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Fixture;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StructureCacheFixtureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testSameStructureListsHandlesOpenedAfterTheSharedEmptyBaseline(string $release): void
    {
        $definition = new DisposableMySql($release);
        $container = Testcontainers::run($definition);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Fixture())->session($endpoint, $native, $release, $definition->supervised());
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_bin');
                $session->run('SET NAMES utf8mb4 COLLATE utf8mb4_bin');
                $session->text = 'unrelated invalid input';
                $initial = (new Native())->observe($native, 'SHOW OPEN TABLES');
                self::assertSame($initial, (new Memory())->observe($session, new ShowOpenTables()));
                self::assertSame([[]], array_column($initial['results'], 'rows'));
                $node = new ShowOpenTables(new Name('cache_domain'));
                $observations = array_map(static function (string $setup) use ($native, $session, $node): array {
                    $native->query($setup);
                    $session->run($setup);
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual), array_column($actual['results'], 'rows')];
                }, ['DO 0', 'CREATE DATABASE cache_domain', 'CREATE TABLE cache_domain.t(a INT)', 'INSERT INTO cache_domain.t VALUES (1), (2)', 'SELECT * FROM cache_domain.t', 'FLUSH TABLES']);

                self::assertSame(array_column($observations, 0), array_column($observations, 1));
                self::assertSame([[[]], [[]], [[]], [[['cache_domain', 't', '0', '0']]], [[['cache_domain', 't', '0', '0']]], [[]]], array_column($observations, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
