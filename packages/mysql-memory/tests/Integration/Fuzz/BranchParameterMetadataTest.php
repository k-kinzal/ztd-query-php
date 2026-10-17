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
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class BranchParameterMetadataTest extends TestCase
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
    public function testPreparedBranchesAndPhoneticNullabilityMatch(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $session = (new Instance($release))->connect();
                $session->query('SET NAMES utf8mb4');
                $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-' . $release);
                $queries = [
                    "SELECT CASE WHEN ? THEN 'utf8mb4_0900_ai_ci' '' WHEN 1 THEN ? END",
                    "SELECT CASE WHEN 1 THEN 'é' ELSE ? END",
                    "SELECT CASE WHEN ? THEN 'é' ELSE NULL END",
                    "SELECT CASE WHEN 1 THEN ? ELSE 'é' END",
                    "SELECT COALESCE('é', ?), IF(1, 'é', ?), IFNULL('é', ?)",
                    'SELECT CASE WHEN 1 THEN 123 ELSE ? END, COALESCE(123, ?)',
                    'SELECT CASE WHEN 1 THEN NULL ELSE ? END',
                    "SELECT FALSE SOUNDS LIKE FALSE, NULL SOUNDS LIKE FALSE, 'a' SOUNDS LIKE 'b'",
                ];
                $observed = array_map(static function (string $sql) use ($native, $session, $semantics): array {
                    $node = $semantics->analyze($sql)->statement;
                    self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $node);
                    $rendered = (new Reference())->sql($node, $session->semantics()->profile(), '?');
                    $expected = (new Native())->observe($native, $rendered, count((new \Fuzz\Structure\Bindings())->nulls($node)));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node, (new \Fuzz\Structure\Audit\Comparison())->parameters($node, $session));

                    return [serialize($expected), serialize($actual)];
                }, $queries);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
