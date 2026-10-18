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
final class InsertValuesStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Dml\Insert\InsertQuery>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('standalone-values-20261018');
        $structures = [];
        for ($trial = 0; $trial < 8; $trial++) {
            $into = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t' . $trial))));
            $rows = new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral((string) $choices->one([3, 7, 11]))]), new M\Query\ValueRow([new M\Literal\NumberLiteral('2')])]);
            $operand = $choices->one([true, false]) ? new M\Routine\Program\Expression\Call\DormantCall(new Name('ABS')) : new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]);
            $source = new M\Dml\Insert\ValuesClauses(null, $rows, [new M\Routine\Program\Selection\DeferredOrderItem($operand)], new M\Query\Clause\RowLimit(new M\Query\Clause\ProgramVariable(new Name('absent'))));
            $structures[] = new M\Dml\Insert\InsertQuery($into, $source);
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Dml\Insert\InsertQuery> $structures Generated write structures reused before and after target-table creation
     */
    #[DataProvider('providerStructures')]
    public function testTheSameValuesSourceUsesOnlyItsRowsAcrossTableHistories(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release, [], ['d']))->connect(database: 'd');
            $observations = array_map(static function (M\Dml\Insert\InsertQuery $insert, int $index) use ($session, $native): array {
                $memory = new Memory();
                $sql = (new Reference())->sql($insert, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $memory, $insert, $sql): array {
                    $native->query($setup);
                    $session->query($setup);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), $memory->observe($session, $insert)];
                }, ['DO 0', 'CREATE TABLE t' . $index . '(id INT)', 'INSERT INTO t' . $index . ' VALUES(100)', 'DROP TABLE t' . $index, 'CREATE TABLE t' . $index . '(id INT)']);
                self::assertSame([1146, null, null, 1146, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                self::assertSame(2, $history[1][0]['results'][0]['affected']);
                return $history;
            }, $structures, array_keys($structures));
            $observations = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
