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
final class SpatialPartHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\QueryRoot, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $items = [];
        foreach (['GEOMETRYN', 'POINTN', 'STARTPOINT', 'ENDPOINT', 'EXTERIORRING', 'INTERIORRINGN', 'NUMPOINTS', 'NUMINTERIORRINGS', 'ISCLOSED'] as $name) {
            $arguments = [new M\Call\CallArgument(new M\Variable\UserVariable(new Name('g')))];
            if (in_array($name, ['GEOMETRYN', 'POINTN', 'INTERIORRINGN'], true)) {
                $arguments[] = new M\Call\CallArgument(new M\Variable\UserVariable(new Name('n')));
            }
            $items[] = new M\Query\SelectExpression(new M\Call\FunctionCall(new Name('ST_' . $name), $arguments), new Name(strtolower($name)));
        }
        $query = new M\QueryRoot(new M\Query\Select([], $items));
        $history = ['SET @g=NULL, @n=1'];
        for ($seed = 0; $seed < 32; $seed++) {
            $choices = new Choices('spatial-parts:' . $seed);
            $points = array_map(static fn (int $i): array => [(float) $choices->integer(-1000, 1000), (float) $choices->integer(-1000, 1000)], range(0, 3));
            $points[3] = $points[0];
            $point = new Geometry(1, [$points[0]]);
            $line = new Geometry(2, $points);
            $polygon = new Geometry(3, [], [$line, $line]);
            $geometry = $choices->one([$point, $line, $polygon, new Geometry(4, [], [$point, $point]), new Geometry(5, [], [$line, $line]), new Geometry(6, [], [$polygon]), new Geometry(7, [], [new Geometry(7), $polygon])]);
            $history[] = "SET @g=X'" . bin2hex(Wkb::write($geometry)) . "', @n=" . $choices->integer(-1, 4);
        }
        $history[] = "SET @g='broken', @n=1";
        $history[] = 'SET @g=NULL';
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $query, $history];
        }
    }

    /**
     * @param list<string> $history Generated geometry values and one-based member indexes
     */
    #[DataProvider('providerHistories')]
    public function testTheSameSemanticQueryReadsGeneratedComponentsWithoutInputSql(string $release, M\QueryRoot $query, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $session = (new Instance($release))->connect();
            $session->query('SET NAMES utf8mb4');
            $observations = array_map(static function (string $setup) use ($native, $session, $query): array {
                $setupNode = $session->analyze($setup)->statement;
                self::assertInstanceOf(M\Statement::class, $setupNode);
                self::assertSame(serialize((new Native())->observe($native, $setup)), serialize((new Memory())->observe($session, $setupNode)), $setup);
                $expected = (new Native())->observe($native, (new Reference())->sql($query, $session->semantics()->profile()));
                $session->text = 'opaque non-SQL label';
                self::assertSame(serialize($expected), serialize((new Memory())->observe($session, $query)), $setup);
                return $expected;
            }, $history);
            self::assertSame([array_fill(0, 9, null)], $observations[0]['results'][0]['rows']);
            self::assertArrayNotHasKey('error', $observations[1]);
            self::assertSame([array_fill(0, 9, null)], $observations[34]['results'][0]['rows']);
            self::assertSame('opaque non-SQL label', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
