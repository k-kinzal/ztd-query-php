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
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RoutineEnumerationStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<CreateFunction|CreateProcedure>, list<int|null>}>
     */
    public static function providerStructures(): iterable
    {
        $enum = new Enumeration(EnumerationKind::Enum, [new Text('a'), new Text('a')]);
        $set = new Enumeration(EnumerationKind::Set, [new Text('a'), new Text('a')]);
        $body = new Evaluation([new SelectExpression(new NumberLiteral('1'))]);
        $statements = [
            new CreateFunction(new QualifiedName(new Name('f')), new ParameterList([]), $enum, new ReturnStatement(new NumberLiteral('1')), characteristics: [new Determinism(true)]),
            new CreateFunction(new QualifiedName(new Name('g')), new ParameterList([]), $set, $body),
            new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([new Parameter(new Name('a'), $enum)]), $body),
        ];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements, [1046, 1291, null, 1291]];
        }
    }

    /**
     * @param list<CreateFunction|CreateProcedure> $statements The same declarations before database selection and under both duplicate-member policies
     * @param list<int|null> $expectedCodes The first request's native errors through the history
     */
    #[DataProvider('providerStructures')]
    public function testSameRoutineDeclarationsFollowSqlModeAndDatabaseState(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE enum_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $native->query("SET sql_mode='STRICT_TRANS_TABLES'");
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run("CREATE DATABASE enum_domain; SET sql_mode='STRICT_TRANS_TABLES'; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (CreateFunction|CreateProcedure $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'USE enum_domain', "SET sql_mode=''", "SET sql_mode='STRICT_TRANS_TABLES'"]);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame($expectedCodes, array_map(static fn (array $state): ?int => $state[0][2], $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
