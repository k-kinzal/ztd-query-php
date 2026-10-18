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
final class ImplicitPartitionStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('implicit-partition-roles-20261019');
        $structures = [];
        for ($trial = 0; $trial < 12; $trial++) {
            $column = new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int));
            $partition = new M\Partition\PartitionClause(new M\Partition\Method\KeyMethod($trial % 2 === 0, null, []), new M\Literal\Numeral((string) $choices->integer(1, 8)), null, []);
            $definition = new M\Routine\Program\Write\DeferredCreateTable(new QualifiedName(new Name('result')), [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), $column)], partitioning: $partition, ifNotExists: $trial % 2 === 0);
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$definition]);
            $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        }
        foreach (['5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures Complete definitions reused across destination states
     */
    #[DataProvider('providerStructures')]
    public function testSameDefinitionsSurviveCreationAndFollowInvocationAndDestinationState(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release, [], ['d']))->connect(database: 'd');
            $observations = array_map(static function (M\Routine\CreateProcedure $create) use ($native, $session): array {
                $memory = new Memory();
                $reference = new Reference();
                $session->text = 'unrelated invalid input';
                $definition = [(new Native())->observe($native, $reference->sql($create, $session->semantics()->profile())), $memory->observe($session, $create)];
                self::assertArrayNotHasKey('error', $definition[0]);
                $call = new M\Dml\ProcedureCall($create->name);
                $sql = $reference->sql($call, $session->semantics()->profile());
                $history = array_map(static function (string $setup) use ($native, $session, $memory, $call, $sql): array {
                    foreach (explode('; ', $setup) as $step) {
                        $native->query($step);
                        $session->query($step);
                    }
                    $session->text = 'unrelated invalid input';
                    return [(new Native())->observe($native, $sql), $memory->observe($session, $call)];
                }, ['DROP TABLE IF EXISTS result; SET @run=0', 'SET @run=1', 'CREATE TABLE result(a INT); INSERT INTO result VALUES(7)', 'DROP TABLE result', 'SET @run=0']);
                self::assertSame([null, 1488, (int) substr($create->name->name->value, 1) % 2 === 0 ? null : 1050, 1488, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return [$definition, ...$history];
            }, $structures);
            $pairs = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($pairs, 0)), array_map(serialize(...), array_column($pairs, 1)));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
