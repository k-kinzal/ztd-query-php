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
final class SpatialInputHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function providerHistories(): iterable
    {
        $history = ['SET @g=NULL, @srid=0'];
        $binary = $history;
        for ($seed = 0; $seed < 24; $seed++) {
            $choices = new Choices('spatial-inputs:' . $seed);
            $points = array_map(static fn (int $index): array => [(float) $choices->integer(-100000, 100000) / 7, (float) $choices->integer(-100000, 100000) / 13], range(0, 3));
            $points[3] = $points[0];
            $point = new Geometry(1, [$points[0]]);
            $line = new Geometry(2, $points);
            $polygon = new Geometry(3, [], [$line]);
            $type = $choices->one([1, 2, 3, 4, 5, 6, 7]);
            $geometry = match ($type) {
                1 => $point,
                2 => $line,
                3 => $polygon,
                4 => new Geometry(4, [], [$point, new Geometry(1, [$points[1]])]),
                5 => new Geometry(5, [], [$line]),
                6 => new Geometry(6, [], [$polygon]),
                7 => new Geometry(7, [], [$point, new Geometry(7), $polygon]),
            };
            $history[] = "SET @g='" . \MySqlMemory\Value\Spatial\Wkt::write($geometry) . "'";
            $binary[] = "SET @g=X'" . bin2hex(Wkb::body($geometry)) . "'";
        }
        $history[] = "SET @g='POINT(1 2)', @srid=1234567";
        $base = 'GEOGCS["format reference",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["G",0],UNIT["degree",0.017453292519943278],';
        $history[] = "CREATE SPATIAL REFERENCE SYSTEM 1234567 NAME 'format reference' DEFINITION '" . $base . 'AXIS["latitude",NORTH],AXIS["longitude",EAST]]' . "'";
        $history[] = "CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 1234567 NAME 'format reference' DEFINITION '" . $base . 'AXIS["longitude",EAST],AXIS["latitude",NORTH]]' . "'";
        $history[] = 'DROP SPATIAL REFERENCE SYSTEM 1234567';
        $history[] = "SET @g='invalid geometry', @srid=0";
        $history[] = 'SET @g=NULL';
        $binary[] = "SET @g=X'" . bin2hex(Wkb::body(new Geometry(1, [[1.0, 2.0]]))) . "', @srid=1234567";
        $binary = [...$binary, ...array_slice($history, 26)];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ':text' => [$release, 'ST_GEOMFROMTEXT', $history];
            yield $release . ':binary' => [$release, 'ST_GEOMFROMWKB', $binary];
        }
    }

    /**
     * @param list<string> $history Values and reference-catalog transitions around the identical semantic query
     */
    #[DataProvider('providerHistories')]
    public function testDirectInputStructuresObserveGeneratedValuesAndReferenceCatalogChanges(string $release, string $function, array $history): void
    {
        $query = new M\QueryRoot(new M\Query\Select([], [new M\Query\SelectExpression(new M\Call\FunctionCall(new Name($function), [new M\Call\CallArgument(new M\Variable\UserVariable(new Name('g'))), new M\Call\CallArgument(new M\Variable\UserVariable(new Name('srid')))]), new Name('geometry'))]));
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
            self::assertSame(3548, $observations[25]['error'][0] ?? null);
            self::assertSame([[Wkb::write(new Geometry(1, [[2.0, 1.0]], [], 1234567))]], $observations[26]['results'][0]['rows']);
            self::assertSame([[Wkb::write(new Geometry(1, [[1.0, 2.0]], [], 1234567))]], $observations[27]['results'][0]['rows']);
            self::assertSame(3548, $observations[28]['error'][0] ?? null);
            self::assertSame(3037, $observations[29]['error'][0] ?? null);
            self::assertSame([[null]], $observations[30]['results'][0]['rows']);
            self::assertSame('opaque non-SQL label', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
