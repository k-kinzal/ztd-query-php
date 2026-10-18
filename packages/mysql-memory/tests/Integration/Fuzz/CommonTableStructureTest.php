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
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class CommonTableStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>}>
     *
     * @throws JsonException When the constructor catalogue cannot be decoded
     * @throws ReflectionException When a model type cannot be inspected
     */
    public static function providerStructures(): iterable
    {
        $catalogue = new Catalogue();
        $metadata = new Metadata();
        $structures = [];
        for ($trial = 0; $trial < 12; $trial++) {
            $construction = new Construction(new Choices('unused-common-table-' . $trial), $catalogue, $metadata);
            $number = $construction->object(M\Literal\NumberLiteral::class, 0);
            assert($number instanceof M\Literal\NumberLiteral);
            $row = new M\Expression\Row([new M\Name\ColumnUse(new Name('unbound')), $number]);
            $input = new M\Relation\TableReference(new QualifiedName(new Name('unopened'), new Name('d')));
            $items = [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('v'))];
            $selection = match ($trial % 4) {
                0 => new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection($row)], $input),
                1 => new M\Routine\Program\DeferredSelection($items, $input, $row),
                2 => new M\Routine\Program\DeferredSelection($items, $input, having: $row),
                3 => new M\Routine\Program\DeferredSelection($items, $input, orderBy: [new M\Routine\Program\Selection\DeferredOrderItem($row)]),
            };
            $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], new M\Query\With\DeferredBody($selection))]);
            $body = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a')))], new M\Relation\TableReference(new QualifiedName(new Name('t'), new Name('d'))), orderBy: [new M\Query\OrderItem(new M\Name\ColumnUse(new Name('a')))]);
            $structures[] = new M\QueryRoot(new M\Query\QueryExpression($with, $body));
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\QueryRoot> $structures The same accepted values across table and column lifetimes
     */
    #[DataProvider('providerStructures')]
    public function testUnusedDefinitionsRemainCompleteWhileReadDependenciesFollowState(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $session = (new Instance($release, [], ['d']))->connect();
            $histories = array_map(static function (M\QueryRoot $statement) use ($session, $native): array {
                $sql = (new Reference())->sql($statement, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $statement, $sql): array {
                    array_map(static function (string $step) use ($session, $native): void {
                        $native->query($step);
                        $session->query($step);
                    }, explode('; ', $setup));
                    $session->text = 'Unrelated input that is not SQL';
                    return [(new Native())->observe($native, $sql), (new Memory())->observe($session, $statement)];
                }, ['DROP TABLE IF EXISTS d.t', 'CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(5),(3)', 'ALTER TABLE d.t CHANGE a b INT', 'ALTER TABLE d.t CHANGE b a INT', 'DELETE FROM d.t; INSERT INTO d.t VALUES(9)']);
                self::assertSame([1146, null, 1054, null, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return $history;
            }, $structures);
            $observations = array_merge(...$histories);
            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)));
            self::assertSame('Unrelated input that is not SQL', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
