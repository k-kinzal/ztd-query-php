<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Program\QueryOrderingHistory;
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
final class QueryOrderingStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>, list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $queries = [];
        $definitions = [];
        foreach ([false, true] as $grouped) {
            foreach (['2', '02'] as $number) {
                $position = new M\Query\Clause\OutputOrdinal(new M\Expression\Grouped(new M\Expression\Operator\Unary(M\Expression\Operator\UnaryOperator::Plus, new M\Literal\NumberLiteral($number))));
                $items = [new M\Query\OrderItem($position), new M\Query\OrderItem(new M\Query\Clause\OutputOrdinal(new M\Literal\NumberLiteral('1')))];
                $table = new M\Relation\TableReference(new QualifiedName(new Name('t')));
                $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Query\Star()], $table, groupBy: $grouped ? new M\Query\Clause\Grouping($items) : null, orderBy: $grouped ? [] : $items));
                $derived = new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('t')))), new Name('q'));
                $queries[] = new M\QueryRoot(new M\Query\Select([], [new M\Name\TableWildcard(new QualifiedName(new Name('q')))], $derived, groupBy: $grouped ? new M\Query\Clause\Grouping($items) : null, orderBy: $grouped ? [] : $items));
                $fixed = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a')))], $table, groupBy: $grouped ? new M\Query\Clause\Grouping($items) : null, orderBy: $grouped ? [] : $items);
                $instruction = new M\Routine\Program\Selection\DeferredQuery($fixed);
                $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$instruction]);
                $definitions[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . count($definitions))), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
                $derivedFixed = new M\Query\Select([], [new M\Query\Star()], new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('a')))], new M\Relation\TableReference(new QualifiedName(new Name('t')))), new Name('q')), groupBy: $grouped ? new M\Query\Clause\Grouping($items) : null, orderBy: $grouped ? [] : $items);
                $derivedInstruction = new M\Routine\Program\Selection\DeferredQuery($derivedFixed);
                $derivedBranch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$derivedInstruction]);
                $definitions[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . count($definitions))), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$derivedBranch]));
            }
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $queries, $definitions];
        }
    }

    /**
     * @param list<M\QueryRoot> $queries The same reads used before and after catalog changes
     * @param list<M\Routine\CreateProcedure> $definitions Stored reads retaining fixed position constraints
     */
    #[DataProvider('providerStructures')]
    public function testTheSameStructuresRetainPositionMeaningAcrossCatalogAndInvocationStates(string $release, array $queries, array $definitions): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->query('USE d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                [$expected, $actual, $conditions] = (new QueryOrderingHistory())->observe($session, $native, $queries, $definitions);
                self::assertSame($expected, $actual);
                self::assertSame([...array_fill(0, count($queries), [1146, 1054, null, 1146]), ...array_fill(0, count($definitions), [null, 1146, 1054, 1054, null])], $conditions);
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
