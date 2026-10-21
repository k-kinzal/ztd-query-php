<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement as M;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class SpatialConstructionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Server\Spatial\CreateSpatialReference}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerReleases(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('row-roles-20261017:156');
        $choices->one($roots);
        $generated = (new Construction($choices, $catalogue, new Metadata()))->object($roots[156], 6);
        Check::invariant($generated instanceof M\Server\Spatial\CreateSpatialReference, 'The recorded whole-model root retains its spatial declaration identity.');
        $explicit = new M\Server\Spatial\CreateSpatialReference(false, true, new M\Literal\Numeral('1000000317'), [new M\Server\Spatial\SpatialAttribute(M\Server\Spatial\SpatialAttributeKind::Name, new M\Literal\Text('candidate')), new M\Server\Spatial\SpatialAttribute(M\Server\Spatial\SpatialAttributeKind::Definition, new M\Literal\Text('GEOGCS[]'))]);
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release . ':explicit' => [$release, $explicit];
            yield $release . ':generated' => [$release, $generated];
        }
    }

    #[DataProvider('providerReleases')]
    public function testTheSameDefinitionTextFollowsTheSpatialCatalog(string $release, M\Server\Spatial\CreateSpatialReference $statement): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $sql = (new Reference())->sql($statement, $session->semantics()->profile());
            $srid = $statement->srid->text;
            $definition = 'GEOGCS["x",DATUM["d",SPHEROID["s",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278],AXIS["Lat",NORTH],AXIS["Lon",EAST]]';
            $pairs = array_map(static function (string $setup) use ($native, $session, $statement, $sql): array {
                $native->query($setup);
                $session->query($setup);
                $session->text = 'unrelated invalid request';
                return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $statement)];
            }, ['DROP SPATIAL REFERENCE SYSTEM IF EXISTS ' . $srid, 'CREATE SPATIAL REFERENCE SYSTEM ' . $srid . " NAME 'existing' DEFINITION '" . $definition . "'", 'DROP SPATIAL REFERENCE SYSTEM ' . $srid]);
            self::assertSame(array_map(serialize(...), array_column($pairs, 0)), array_map(serialize(...), array_column($pairs, 1)));
            self::assertSame([3517, null, 3517], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $pairs));
            self::assertSame('unrelated invalid request', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
