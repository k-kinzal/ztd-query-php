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
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatus;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class StructureBinaryLogFixtureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.7.44', '8.0.44', '8.2.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testSameStructureObservesLogRotationAfterTheSharedBaseline(string $release): void
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
                $node = new ShowBinaryLogStatus((new Releases())->binaryLogs($session->semantics()->profile()->grammar));
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                $initial = (new Native())->observe($native, $sql);
                self::assertSame($initial, (new Memory())->observe($session, $node));
                $native->query('FLUSH BINARY LOGS');
                $session->run('FLUSH BINARY LOGS');
                $session->text = 'unrelated invalid input';
                $rotated = (new Native())->observe($native, $sql);
                self::assertSame($rotated, (new Memory())->observe($session, $node));
                self::assertSame(($session->variables->read('log_bin') === 'ON'), $initial['results'] !== $rotated['results']);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
