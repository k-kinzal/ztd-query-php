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
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SpatialConstructionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testTheSameDefinitionTextFollowsTheSpatialCatalog(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $statement = new M\Server\Spatial\CreateSpatialReference(false, true, new M\Literal\Numeral('1000000317'), [new M\Server\Spatial\SpatialAttribute(M\Server\Spatial\SpatialAttributeKind::Name, new M\Literal\Text('candidate')), new M\Server\Spatial\SpatialAttribute(M\Server\Spatial\SpatialAttributeKind::Definition, new M\Literal\Text('GEOGCS[]'))]);
            $sql = (new Reference())->sql($statement, $session->semantics()->profile());
            $definition = 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]';
            $pairs = array_map(static function (string $setup) use ($native, $session, $statement, $sql): array {
                $native->query($setup);
                $session->query($setup);
                $session->text = 'unrelated invalid request';
                return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $statement)];
            }, ['DROP SPATIAL REFERENCE SYSTEM IF EXISTS 1000000317', "CREATE SPATIAL REFERENCE SYSTEM 1000000317 NAME 'existing' DEFINITION '" . $definition . "'", 'DROP SPATIAL REFERENCE SYSTEM 1000000317']);
            self::assertSame(array_map(serialize(...), array_column($pairs, 0)), array_map(serialize(...), array_column($pairs, 1)));
            self::assertSame([3517, null, 3517], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $pairs));
            self::assertSame('unrelated invalid request', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
