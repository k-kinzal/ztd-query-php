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
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class HistogramStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testSameHistogramRequestTracksTableAndStatisticsState(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE histogram_domain');
                $native->select_db('histogram_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE histogram_domain; USE histogram_domain; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $columns = [new Name('z'), new Name('a')];
                $statements = [
                    new AnalyzeTable(false, [new MaintainedTable(new QualifiedName(new Name('t')))], new DropHistogram($columns)),
                    new AnalyzeTable(false, [new MaintainedTable(new QualifiedName(new Name('t'), new Name('absent')))], new DropHistogram($columns)),
                ];
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (AnalyzeTable $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['results'][0]['rows'] ?? null];
                    }, $statements);
                }, ['DO 0', 'CREATE TABLE t (a INT, Z INT)', 'ANALYZE TABLE t UPDATE HISTOGRAM ON a,z', 'DO 0', 'DROP TABLE t', 'CREATE VIEW t AS SELECT 1 AS a', 'DROP VIEW t', 'CREATE TEMPORARY TABLE t(a INT)']);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame([
                    ['histogram_domain.t', 'histogram', 'status', "Histogram statistics removed for column 'a'."],
                    ['histogram_domain.t', 'histogram', 'status', "Histogram statistics removed for column 'z'."],
                ], $observed[2][0][2]);
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
