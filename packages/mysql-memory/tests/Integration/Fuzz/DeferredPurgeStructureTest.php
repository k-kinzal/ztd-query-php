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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredLike;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DeferredPurgeStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('purge-cutoff-20261018');
        $structures = [];
        for ($trial = 0; $trial < 8; $trial++) {
            $row = new Row(array_map(static fn () => new NumberLiteral((string) $choices->integer(0, 1000)), range(1, $choices->integer(2, 4))));
            $body = new DeferredPurge($trial % 2 === 0 ? $row : new DeferredLike(new NumberLiteral('1'), $row));
            $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
            $structures[] = new CreateProcedure(new QualifiedName(new Name('p' . $trial)), new ParameterList([]), new IfStatement([$branch]));
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<CreateProcedure> $structures Directly constructed programs reused across session histories
     */
    #[DataProvider('providerStructures')]
    public function testTheSameProgramAndCallCanSkipReachAndSkipItsCutoff(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance($release, [], ['d']))->connect(database: 'd');
            $session->text = 'unrelated invalid input';
            $observations = array_map(static function (CreateProcedure $create) use ($session, $native): array {
                $memory = new Memory();
                $reference = new Reference();
                $definition = [(new Native())->observe($native, $reference->sql($create, $session->semantics()->profile())), $memory->observe($session, $create)];
                self::assertArrayNotHasKey('error', $definition[0]);
                $call = new ProcedureCall($create->name);
                $sql = $reference->sql($call, $session->semantics()->profile());
                $history = array_map(static function (int $run) use ($session, $native, $memory, $call, $sql): array {
                    $set = new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables([new \SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))]);
                    $native->query('SET @run=' . $run);
                    self::assertArrayNotHasKey('error', $memory->observe($session, $set));
                    return [(new Native())->observe($native, $sql), $memory->observe($session, $call)];
                }, [0, 1, 0]);
                self::assertSame([null, 1241, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
                return [$definition, ...$history];
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
