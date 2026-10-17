<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use LogicException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantAs;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GrantAsStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>}>
     * @throws JsonException When the value schema is invalid
     * @throws ReflectionException When a constructor type cannot be read
     * @throws LogicException When construction returns another type
     */
    public static function providerHistories(): iterable
    {
        $history = [];
        for ($seed = 0; $seed < 20; $seed++) {
            $body = (new Construction(new Choices('deferred-grant:' . $seed), new Catalogue(), new Metadata()))->object(DeferredGrantAs::class, 4);
            if (!$body instanceof DeferredGrantAs) {
                throw new LogicException('Construction must return the requested program member.');
            }
            $name = new QualifiedName(new Name('p' . $seed));
            $create = new CreateProcedure($name, new ParameterList([]), $body);
            $call = new ProcedureCall($name);
            array_push($history, $call, $create, $call, $create);
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $history];
        }
    }

    /**
     * @param list<Statement> $history Every generated body, with identical create/call objects across states
     */
    #[DataProvider('providerHistories')]
    public function testGeneratedInstructionsPreserveNativeDefinitionAndInvocationOutcomes(string $release, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET SESSION sql_mode='', collation_connection='utf8mb4_bin'");
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect(database: 'd');
                (new SessionSetup())->apply($session, '');
                $session->text = 'unrelated invalid input';
                $observed = array_map(static function (Statement $node) use ($native, $session): array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $history);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame(array_merge(...array_fill(0, 20, [1305, null, 3835, 1304])), array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
