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
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class WindowClauseStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('window-clause-structure-20261018');
        $kinds = [WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue, WindowFunctionKind::Lead, WindowFunctionKind::Lag, WindowFunctionKind::NthValue];
        $structures = [];
        for ($trial = 0; $trial < 15; $trial++) {
            $kind = $kinds[$trial % count($kinds)];
            $arguments = [new ColumnUse(new Name('v'))];
            if ($kind === WindowFunctionKind::NthValue) {
                $arguments[] = new NumberLiteral((string) $choices->integer(1, 5));
            }
            $window = new WindowFunction($kind, $arguments, new WindowSpec());
            $predicate = new Row(array_map(static fn () => new NumberLiteral((string) $choices->integer(0, 1000)), range(1, $choices->integer(2, 4))));
            $selection = new DeferredSelection([new SelectExpression($window)], new TableReference(new QualifiedName(new Name('t'))), $predicate);
            $structures[] = new CreateProcedure(new QualifiedName(new Name('p' . $trial)), new ParameterList([]), new IfStatement([new ConditionalBranch(new UserVariable(new Name('run')), [$selection])]));
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<CreateProcedure> $structures Directly constructed definitions, reused through every history
     */
    #[DataProvider('providerStructures')]
    public function testTheSameStructuresPreserveWindowDependenciesBeforeADeferredClause(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release))->connect();
            $session->query('CREATE DATABASE d; USE d');
            $observations = array_map(static function (CreateProcedure $definition) use ($native, $session): array {
                $sql = (new Reference())->sql($definition, $session->semantics()->profile());
                $session->text = 'unrelated invalid input';
                $creation = [(new Native())->observe($native, $sql), (new Memory())->observe($session, $definition)];
                $call = new ProcedureCall($definition->name);
                $callSql = (new Reference())->sql($call, $session->semantics()->profile());
                $transitions = ['SET @run=0', 'SET @run=1', 'CREATE TABLE t(v INT, other INT)', 'INSERT INTO t VALUES(1,0),(2,0)', 'ALTER TABLE t DROP COLUMN v', 'SET @run=0'];
                $history = array_map(static function (string $setup) use ($session, $native, $call, $callSql): array {
                    $native->query($setup);
                    $session->query($setup);
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $callSql), (new Memory())->observe($session, $call)];
                }, $transitions);
                self::assertArrayNotHasKey('error', $creation[0]);
                self::assertSame([null, 1146, 1241, 1241, 1054, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                self::assertSame('unrelated invalid input', $session->text);
                $native->query('DROP TABLE t');
                $session->query('DROP TABLE t');
                return [$creation, ...$history];
            }, $structures);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
