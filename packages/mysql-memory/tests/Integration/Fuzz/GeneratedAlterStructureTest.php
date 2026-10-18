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
final class GeneratedAlterStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{list<M\Routine\CreateProcedure>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new Choices('legacy-generated-alterations-20261019');
        $structures = [];
        for ($trial = 0; $trial < 12; $trial++) {
            $function = ['USER', 'RAND', 'NOW'][$choices->integer(0, 2)];
            $expression = match ($function) {
                'USER' => new M\Call\KeywordCall(M\Call\KeywordFunction::User, []),
                'NOW' => new M\Call\ClockCall(M\Call\Clock::Now),
                'RAND' => new M\Call\FunctionCall(new Name($function), []),
            };
            $column = new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), $expression, storage: $trial % 2 === 0 ? M\Table\Column\Kind\GeneratedStorage::Virtual : M\Table\Column\Kind\GeneratedStorage::Stored);
            $definition = new M\Routine\Program\Write\DeferredAlterTable(new QualifiedName(new Name('result')), [new M\Alter\Column\ChangeColumn(null, new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), $column))]);
            $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$definition]);
            $structures[] = new M\Routine\CreateProcedure(new QualifiedName(new Name('p' . $trial)), new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        }
        yield '5.7.44' => [$structures];
    }

    /**
     * @param list<M\Routine\CreateProcedure> $structures Complete definitions reused across destination states
     */
    #[DataProvider('providerStructures')]
    public function testSameDefinitionsSurviveCreationAndFollowInvocationAndDestinationState(array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql('5.7.44'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->query('CREATE DATABASE d');
            $native->query('USE d');
            $session = (new Instance('5.7.44', [], ['d']))->connect(database: 'd');
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
                }, ['DROP TABLE IF EXISTS result; SET @run=0', 'SET @run=1', 'CREATE TABLE result(g INT); INSERT INTO result VALUES(1),(2)', 'DROP TABLE result; CREATE TABLE result(a INT, g INT AS(a+1)); INSERT INTO result(a) VALUES(1),(2)', 'DROP TABLE result', 'SET @run=0']);
                self::assertSame([null, 1146, (int) substr($create->name->name->value, 1) % 2 === 0 ? 3106 : 3102, (int) substr($create->name->name->value, 1) % 2 === 0 ? 3102 : 3106, 1146, null], array_map(static fn (array $pair): ?int => $pair[0]['error'][0] ?? null, $history));
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
