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
final class QualifyOperandStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('qualify-operands-20261019');
        $structures = [];
        for ($trial = 0; $trial < 12; $trial++) {
            $table = new M\Relation\TableReference(new QualifiedName(new Name('t' . $trial)));
            $argument = new M\Expression\Row([new M\Name\ColumnUse(new Name('id')), new M\Literal\NumberLiteral('2')]);
            $projection = $choices->one([new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('id'))), new M\Query\Star(), new M\Name\TableWildcard(new QualifiedName(new Name('absent_alias'))), new M\Routine\Program\DeferredProjection($argument)]);
            $body = new M\Routine\Program\DeferredSelection([$projection], $table, qualify: new M\Expression\Row([new M\Name\ColumnUse(new Name('id')), new M\Literal\NumberLiteral('2')]));
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$body]);
            $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        }
        foreach (['8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures Generated QUALIFY instructions reused across reachability, table and column histories
     */
    #[DataProvider('providerStructures')]
    public function testTheSamePredicateRetainsItsDependenciesAndBranchReachability(string $release, array $structures): void
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
                }, ['SET @run=0', 'SET @run=1', 'CREATE TABLE t' . $index . '(x INT)', 'ALTER TABLE t' . $index . ' ADD id INT', 'ALTER TABLE t' . $index . ' ADD order_key INT', 'INSERT INTO t' . $index . ' VALUES(1,2,3)', 'SET @run=0']);
                self::assertArrayNotHasKey('error', $history[0][0]);
                self::assertArrayHasKey('error', $history[5][0]);
                self::assertArrayNotHasKey('error', $history[6][0]);
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
