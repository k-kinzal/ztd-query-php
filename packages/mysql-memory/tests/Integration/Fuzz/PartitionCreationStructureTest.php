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
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesIn;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class PartitionCreationStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<CreateTable>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('partition-creation-20261018');
        $structures = [];
        foreach ([null, 0, 1, 2] as $query) {
            foreach ([false, true] as $constant) {
                foreach (['hash', 'range', 'list'] as $kind) {
                    $value = $choices->integer(1, 100);
                    $expression = $constant ? new NumberLiteral((string) $value) : new ColumnUse(new Name('a'));
                    $method = $kind === 'hash' ? new HashMethod(false, $expression) : new ExpressionMethod($kind === 'range' ? PartitionKind::Range : PartitionKind::List, $expression);
                    $bound = new PartitionRow([new NumberLiteral((string) ($kind === 'range' ? $value + 1 : $value))]);
                    $definitions = $kind === 'hash' ? [] : [new PartitionDefinition(new Name('p'), $kind === 'range' ? new LessThan($bound) : new ValuesIn($bound), [], [])];
                    $elements = $query === null || $choices->integer(0, 1) === 0 ? [new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)))] : [];
                    $structures[] = new CreateTable(new QualifiedName(new Name('t'), new Name('d')), $elements, partitioning: new PartitionClause($method, null, null, $definitions), query: $query === null ? null : new TableQuery(new Select([], [new SelectExpression(new NumberLiteral((string) ($value + min($query, 1))), new Name('a'))]), $query === 2 ? DuplicateHandling::Ignore : null), ifNotExists: true);
                }
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<CreateTable> $structures Complete definitions, with and without source rows
     */
    #[DataProvider('providerStructures')]
    public function testTheSameDefinitionRetainsPartitioningAcrossTableLifetimes(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $session = (new Instance($release, [], ['d']))->connect();
            $observations = array_map(static function (CreateTable $create) use ($session, $native): array {
                $sql = (new Reference())->sql($create, $session->semantics()->profile());
                $partition = $create->partitioning instanceof PartitionClause && $create->partitioning->method instanceof HashMethod ? 'p0' : 'p';
                $read = new QueryRoot(new Select([], [new SelectExpression(new ColumnUse(new Name('a')))], new TableReference(new QualifiedName(new Name('t'), new Name('d')), partitions: [new Name($partition)]), orderBy: [new OrderItem(new ColumnUse(new Name('a')))]));
                $readSql = (new Reference())->sql($read, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $create, $sql, $read, $readSql): array {
                    foreach (explode('; ', $setup) as $step) {
                        $native->query($step);
                        $session->query($step);
                    }
                    $session->text = 'unrelated invalid input';
                    $pair = [(new Native())->observe($native, $sql), (new Memory())->observe($session, $create)];
                    return [$pair, [(new Native())->observe($native, $readSql), (new Memory())->observe($session, $read)]];
                }, ['DROP TABLE IF EXISTS d.t', 'DROP TABLE IF EXISTS d.t; CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES(7),(9)', 'DROP TABLE d.t']);
                self::assertNull($history[1][0][0]['error'] ?? null, $sql);
                self::assertSame([['Note', '1050', "Table 't' already exists"]], $history[1][0][0]['warnings'], $sql);
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
