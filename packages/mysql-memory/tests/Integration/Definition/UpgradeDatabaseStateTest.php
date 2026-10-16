<?php

declare(strict_types=1);

namespace Tests\Integration\Definition;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\LegacyDatabaseName;
use SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class UpgradeDatabaseStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, UpgradeDatabaseName}>
     */
    public static function providerNames(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, new UpgradeDatabaseName(new LegacyDatabaseName(new Name('old-name')))];
        }
    }

    #[DataProvider('providerNames')]
    public function testSameStructureMovesPopulatedStateOnce(string $release, UpgradeDatabaseName $upgrade): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect('root', 'localhost');
                $sql = (new Reference())->sql($upgrade, $session->semantics()->profile());
                $fixtures = [[], ['CREATE DATABASE `#mysql50#old-name`', 'CREATE TABLE `#mysql50#old-name`.t(a INT)', 'INSERT INTO `#mysql50#old-name`.t VALUES(2)', 'USE `#mysql50#old-name`'], []];
                $reader = $session->semantics()->analyze('SELECT * FROM `old-name`.t')->statement;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $reader);
                $observed = array_map(static function (array $fixture) use ($native, $session, $upgrade, $sql, $reader): array {
                    array_map(static fn (string $text): bool|mysqli_result => $native->query($text), $fixture);
                    array_map($session->query(...), $fixture);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $upgrade);

                    return [serialize([$expected, (new Native())->observe($native, 'SELECT * FROM `old-name`.t')]), serialize([$actual, (new Memory())->observe($session, $reader)]), $expected['error'][0] ?? null];
                }, $fixtures);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1049, null, 1049], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
