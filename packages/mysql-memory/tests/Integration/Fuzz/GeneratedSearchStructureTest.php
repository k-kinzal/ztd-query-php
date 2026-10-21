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
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedSearchStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{list<array{M\Routine\CreateProcedure, int}>}>
     */
    public static function providerStructures(): iterable
    {
        $structures = [];
        foreach ([3102, 1210, 1241, 1054] as $index => $code) {
            $columns = [new M\Name\ColumnUse(new Name($code === 1054 ? 'missing' : 'a'))];
            $expression = $code === 1241
                ? new M\Routine\Program\Expression\Access\DeferredFullTextSearch($columns, new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))
                : new M\Expression\Access\FullTextSearch($columns, $code === 1210 ? new M\Call\FunctionCall(new Name('RAND')) : new M\Call\KeywordCall(M\Call\KeywordFunction::User, []));
            $table = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('t')), [
                new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Character(M\Type\Kind\CharacterKind::Text))),
                new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $expression)),
            ], ifNotExists: true);
            $structures[] = [new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $index)), new M\Routine\ParameterList([]), $table), $code];
        }
        yield '5.7.44' => [$structures];
    }

    /**
     * @param list<array{M\Routine\CreateProcedure, int}> $structures Complete deferred definitions, constructed without parsing
     */
    #[DataProvider('providerStructures')]
    public function testSameDefinitionsFollowAbsentPopulatedAndRemovedDestinationStates(array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql('5.7.44'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
                array_map(static function (array $entry) use ($native, $session): void {
                    [$definition, $code] = $entry;
                    $reference = new Reference();
                    self::assertSame((new Native())->observe($native, $reference->sql($definition, $session->semantics()->profile())), (new Memory())->observe($session, $definition));
                    $call = new M\Dml\ProcedureCall($definition->name);
                    $sql = $reference->sql($call, $session->semantics()->profile());
                    $history = array_map(static function (array $setup) use ($native, $session, $call, $sql): array {
                        array_map(static function (string $step) use ($native, $session): void {
                            $native->query($step);
                            $session->query($step);
                        }, $setup);
                        $session->text = 'opaque request label';
                        $expected = (new Native())->observe($native, $sql);
                        self::assertSame($expected, (new Memory())->observe($session, $call));
                        return $expected;
                    }, [[], ['CREATE TABLE t(a TEXT)', "INSERT INTO t VALUES('one'),('two')"], ['DROP TABLE t']]);
                    self::assertSame([$code, null, $code], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                    self::assertSame('opaque request label', $session->text);
                }, $structures);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
