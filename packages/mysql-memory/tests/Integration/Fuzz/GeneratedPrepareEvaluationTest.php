<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedPrepareEvaluationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement, int}>
     * @throws JsonException When the reviewed semantic inventory cannot be read
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerPayloads(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        foreach ([60 => 1064] as $index => $error) {
            $choices = new Choices('row-roles-20261017:' . $index);
            $choices->one($roots);
            $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[$index], 6);
            Check::invariant($node instanceof M\Dml\Prepared\Prepare, 'The recorded roots retain their executable payload identities.');
            foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
                yield $release . ':' . $index => [$release, $node, $error];
            }
        }
    }

    #[DataProvider('providerPayloads')]
    public function testTheSamePayloadIsInterpretedOnlyWhenControlReachesItsInstruction(string $release, M\Statement $node, int $code): void
    {
        $name = new QualifiedName(new Name('p'), new Name('d'));
        $branch = new M\Routine\Program\Flow\ConditionalBranch(new M\Variable\UserVariable(new Name('run')), [$node]);
        $create = new M\Routine\CreateProcedure($name, new M\Routine\ParameterList([]), new M\Routine\Program\Flow\IfStatement([$branch]));
        self::assertSame($node, $branch->statements[0]);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->set_charset('utf8mb4');
                $session = (new Instance($release, [], ['d']))->connect();
                $session->query('SET NAMES utf8mb4');
                $reference = new Reference();
                $definition = (new Native())->observe($native, $reference->sql($create, $session->semantics()->profile()));
                $session->text = 'opaque request label';
                self::assertSame($definition, (new Memory())->observe($session, $create));
                self::assertArrayNotHasKey('error', $definition);
                $call = new M\Dml\ProcedureCall($name);
                $history = array_map(static function (int $run) use ($native, $session, $reference, $call): array {
                    $setup = 'SET @run=' . $run;
                    $assignment = $session->analyze($setup)->statement;
                    self::assertInstanceOf(M\Statement::class, $assignment);
                    self::assertSame((new Native())->observe($native, $setup), (new Memory())->observe($session, $assignment));
                    $expected = (new Native())->observe($native, $reference->sql($call, $session->semantics()->profile()));
                    $session->text = 'opaque request label';
                    self::assertSame($expected, (new Memory())->observe($session, $call));
                    return $expected;
                }, [0, 1, 0]);
                self::assertSame([null, $code, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame('opaque request label', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
