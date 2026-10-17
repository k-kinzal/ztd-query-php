<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Signal;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SqlState;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerAction;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ConditionScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $signal = new Resignal(new ConditionName(new Name('raised')));
            $condition = new ConditionDeclaration(new Name('raised'), new SqlState(new Text('45001')));
            $plainName = new QualifiedName(new Name('plain'), new Name('d'));
            $handlerName = new QualifiedName(new Name('handled'), new Name('d'));
            $plain = new CreateProcedure($plainName, new ParameterList([]), new Block([$condition], [$signal]));
            $handler = new HandlerDeclaration(HandlerAction::Exit, [new SqlState(new Text('45000'))], $signal);
            $handled = new CreateProcedure($handlerName, new ParameterList([]), new Block([$condition, $handler], [new Signal(new SqlState(new Text('45000')))]));
            $silentName = new QualifiedName(new Name('silent'), new Name('d'));
            $silent = new CreateProcedure($silentName, new ParameterList([]), new Block([new HandlerDeclaration(HandlerAction::Continue, [new SqlState(new Text('45000'))], new Block())], [new Signal(new SqlState(new Text('45000')))]));
            yield $release => [$release, [$signal, $plain, new ProcedureCall($plainName), $handled, new ProcedureCall($handlerName), $signal, $silent, new ProcedureCall($silentName)]];
        }
    }

    /**
     * @param list<Statement> $nodes The same RESIGNAL in no scope, a declaration scope and an active handler
     */
    #[DataProvider('providerStructures')]
    public function testSameConditionUseResolvesInItsLexicalAndRuntimeScopes(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci', 'CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', 'USE d'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $profile = $session->semantics()->profile();
                $expected = array_map(static fn (Statement $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile))), $nodes);
                $session->text = 'unrelated invalid input';
                $actual = array_map(static function (Statement $node) use ($session): array {
                    $result = (new Memory())->observe($session, $node);

                    return [serialize($result), $result['error'][0] ?? null];
                }, $nodes);

                self::assertSame($expected, array_column($actual, 0));
                self::assertSame([1319, null, 1645, null, 1644, 1319, null, null], array_column($actual, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
