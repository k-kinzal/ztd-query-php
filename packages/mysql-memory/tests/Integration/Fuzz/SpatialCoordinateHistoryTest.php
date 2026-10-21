<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SpatialCoordinateHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $history = ['SET @g=NULL, @n=1'];
        for ($seed = 0; $seed < 32; $seed++) {
            $choices = new Choices('spatial-coordinates:' . $seed);
            $point = [(float) $choices->integer(-180, 180), (float) $choices->integer(-100, 100)];
            $geometry = new Geometry(1, [$point], [], $choices->one([0, 4326, 4807, 12345]));
            $history[] = "SET @g=X'" . bin2hex(Wkb::write($geometry)) . "', @n=" . $choices->integer(-200, 200);
        }
        $history[] = "SET @g='broken', @n=1";
        $history[] = 'SET @g=NULL';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $queries = [];
            $legacy = version_compare($release, '8.0', '<');
            foreach ($legacy ? ['X', 'Y', 'ST_X', 'ST_Y'] : ['ST_X', 'ST_Y', 'ST_LONGITUDE', 'ST_LATITUDE'] as $name) {
                $arguments = [new M\Call\CallArgument(new M\Variable\UserVariable(new Name('g')))];
                $call = new M\Call\FunctionCall(new Name($name), $arguments);
                $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($call, new Name('v'))]));
                if (!$legacy) {
                    $arguments[] = new M\Call\CallArgument(new M\Variable\UserVariable(new Name('n')));
                    $setter = new M\Call\FunctionCall(new Name($name), $arguments);
                    $hex = new M\Call\FunctionCall(new Name('HEX'), [new M\Call\CallArgument($setter)]);
                    $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression($hex, new Name('v'))]));
                }
            }
            yield $release => [$release, $queries, $history];
        }
    }

    /**
     * @param list<M\QueryRoot> $queries Coordinate readers and setters constructed without SQL
     * @param list<string> $history Generated points, reference identifiers and replacement coordinates
     */
    #[DataProvider('providerHistories')]
    public function testTheSameSemanticQueriesReadAndReplaceGeneratedCoordinatesWithoutInputSql(string $release, array $queries, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $session = (new Instance($release))->connect();
            $session->query('SET NAMES utf8mb4');
            $observations = array_map(static function (string $setup) use ($native, $session, $queries): array {
                $setupNode = $session->analyze($setup)->statement;
                self::assertInstanceOf(M\Statement::class, $setupNode);
                self::assertSame(serialize((new Native())->observe($native, $setup)), serialize((new Memory())->observe($session, $setupNode)), $setup);
                return array_map(static function (M\QueryRoot $query) use ($native, $session, $setup): array {
                    $sql = (new Reference())->sql($query, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'opaque non-SQL label';
                    self::assertSame(serialize($expected), serialize((new Memory())->observe($session, $query)), $setup . ': ' . $sql);
                    return $expected;
                }, $queries);
            }, $history);
            self::assertSame([[null]], $observations[0][0]['results'][0]['rows']);
            self::assertSame([[null]], $observations[34][0]['results'][0]['rows']);
            self::assertSame('opaque non-SQL label', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
