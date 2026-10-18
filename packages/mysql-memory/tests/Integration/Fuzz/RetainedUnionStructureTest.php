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
final class RetainedUnionStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('retained-union-20261019');
        foreach (['5.6.51', '5.7.44'] as $release) {
            $structures = [];
            for ($trial = 0; $trial < 12; $trial++) {
                $table = new M\Relation\TableReference(new QualifiedName(new Name('t' . $trial)));
                $number = new M\Literal\NumberLiteral((string) $choices->one([3, 7, 11]));
                $row = new M\Expression\Row([new M\Name\ColumnUse(new Name('id')), $number]);
                $one = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral($number->text))]);
                $limit = new M\Query\Clause\RowLimit(new M\Literal\NumberLiteral('1'));
                $right = new M\Query\With\DeferredBody(new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection($row)], $table, limit: $limit));
                $sortBody = new M\Query\Set\SetOperation($one, M\Query\Set\SetOperator::Union, null, new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('id')))], $table));
                $sort = new M\Query\QueryExpression(null, $sortBody, [new M\Routine\Program\Selection\DeferredOrderItem(new M\Expression\Row([new M\Literal\NumberLiteral($number->text), new M\Literal\NumberLiteral($number->text)]))]);
                $plain = new M\Query\Select([], [new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('id')))], new M\Relation\TableReference(new QualifiedName(new Name('t' . $trial))), orderBy: [new M\Routine\Program\Selection\DeferredOrderItem(new M\Expression\Row([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')]))]);
                $query = $release === '5.7.44' ? ($trial % 2 === 0 ? $sort : $plain) : match ($trial % 4) {
                    0 => $sort,
                    3 => $plain,
                    1 => new M\Query\Set\SetOperation(new M\Query\Set\LeadingUnion($one, null, $right), M\Query\Set\SetOperator::Union, null, new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('13'))])),
                    2 => new M\Query\With\DeferredBody(new M\Routine\Program\DeferredSelection([new M\Routine\Program\DeferredProjection(new M\Routine\Program\Expression\DeferredSubquery(new M\Query\Set\OrderedSetOperation($one, M\Query\Set\SetOperator::Union, null, $right, limit: new M\Query\Clause\RowLimit(new M\Literal\NumberLiteral('1')))))])),
                };
                $body = $query instanceof M\Query\With\DeferredBody ? $query->selection : new M\Routine\Program\Selection\DeferredQuery($query);
                $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$trial < 8 ? $body : new M\Routine\Program\Cursor\OpenCursor(new Name('c'))]);
                $conditional = new M\Routine\Program\Flow\IfStatement([$branch]);
                $instruction = $trial < 8 ? $conditional : new M\Routine\Program\Block([new M\Routine\Program\Cursor\CursorDeclaration(new Name('c'), $body)], [$conditional]);
                $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), $instruction);
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures Generated expression structures reused across branch and table histories
     */
    #[DataProvider('providerStructures')]
    public function testTheSameUnionRetainsItsOperandsAcrossReachedAndSkippedBranches(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release, [], ['d']))->connect(database: 'd');
            $observations = array_map(static function (M\Routine\CreateProcedure $create, int $index) use ($session, $native): array {
                $memory = new Memory();
                $reference = new Reference();
                $session->text = 'unrelated invalid input';
                $definition = [(new Native())->observe($native, $reference->sql($create, $session->semantics()->profile())), $memory->observe($session, $create)];
                self::assertArrayNotHasKey('error', $definition[0]);
                $call = new M\Dml\ProcedureCall($create->name);
                $sql = $reference->sql($call, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($session, $native, $memory, $call, $sql): array {
                    $native->query($setup);
                    $session->query($setup);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), $memory->observe($session, $call)];
                }, ['SET @run=0', 'SET @run=1', 'CREATE TABLE t' . $index . '(id INT, txt TEXT)', 'INSERT INTO t' . $index . " VALUES(1,'text')", 'SET @run=0']);
                self::assertSame([null, 1146, 1241, 1241, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return [$definition, ...$history];
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
