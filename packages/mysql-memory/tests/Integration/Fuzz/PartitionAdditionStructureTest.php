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
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\AddPartition;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Partition\SubpartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class PartitionAdditionStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{AlterTable, string}>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('partition-addition-20261018');
        $structures = [];
        for ($trial = 0; $trial < 8; $trial++) {
            $count = $choices->integer(1, 3);
            $definitions = [new PartitionDefinition(new Name('added'), new LessThan(new PartitionRow([new NumberLiteral((string) $choices->integer(20, 1000))])), [], array_map(static fn (int $sub): SubpartitionDefinition => new SubpartitionDefinition(new Name('added_' . $sub), []), range(1, $count)))];
            $change = new AlterTable(new QualifiedName(new Name('t'), new Name('d')), [new AddPartition(false, $definitions)]);
            $setup = 'CREATE TABLE d.t(a INT) PARTITION BY RANGE(a) SUBPARTITION BY HASH(a) (PARTITION old VALUES LESS THAN(10) (' . implode(',', array_map(static fn (int $index): string => 'SUBPARTITION original' . $index, range(1, $count))) . ')); INSERT INTO d.t VALUES(1),(9)';
            $structures[] = [$change, $setup];
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<array{AlterTable, string}> $structures The same actions across table lifetimes
     */
    #[DataProvider('providerStructures')]
    public function testTheSamePartitionChangeResolvesAgainstEachTableState(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $session = (new Instance($release, [], ['d']))->connect();
            $read = new QueryRoot(new Select([], [new SelectExpression(new ColumnUse(new Name('a')))], new TableReference(new QualifiedName(new Name('t'), new Name('d'))), orderBy: [new OrderItem(new ColumnUse(new Name('a')))]));
            $observations = array_map(static function (array $case) use ($session, $native, $read): array {
                [$change, $setup] = $case;
                $reference = new Reference();
                $sql = $reference->sql($change, $session->semantics()->profile());
                $readSql = $reference->sql($read, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $change, $sql, $read, $readSql): array {
                    array_map(static function (string $step) use ($session, $native): void {
                        $native->query($step);
                        $session->query($step);
                    }, explode('; ', $setup));
                    $session->text = 'unrelated invalid input';
                    return [[(new Native())->observe($native, $sql), (new Memory())->observe($session, $change)], [(new Native())->observe($native, $readSql), (new Memory())->observe($session, $read)]];
                }, ['DROP TABLE IF EXISTS d.t', 'CREATE TABLE d.t(a INT)', 'DROP TABLE d.t; ' . $setup, 'DO 0', 'DROP TABLE d.t; CREATE TABLE d.t(a INT) PARTITION BY LIST(a) (PARTITION old VALUES IN(1,9)); INSERT INTO d.t VALUES(1),(9)', 'DROP TABLE d.t; ' . $setup]);
                self::assertSame([1146, 1505, null, 1517, 1480, null], array_map(static fn (array $pair): ?int => $pair[0][0]['error'][0] ?? null, $history));
                return array_merge(...$history);
            }, $structures);
            $observations = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($observations, 0)), array_map(serialize(...), array_column($observations, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
