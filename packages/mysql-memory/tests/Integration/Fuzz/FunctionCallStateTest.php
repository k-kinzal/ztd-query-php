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
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FunctionCallStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement|FunctionCall>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([new FunctionCall(new Name('ABS'), schema: new Name('d')), new FunctionCall(new Name(str_starts_with($release, '5.') ? 'REGEXP_LIKE' : 'X'))] as $call) {
                $name = new QualifiedName(new Name($call->name->value), new Name('d'));
                $create = new CreateFunction($name, new ParameterList([]), new Integral(IntegralKind::Int), new ReturnStatement(new NumberLiteral('7')), characteristics: [new Determinism(true)]);
                yield $release . ':' . $call->name->value => [$release, [$call, $create, $call, new DropProgram(ProgramKind::Function, new QualifiedName(new Name($call->name->value), new Name('d'))), $call]];
            }
        }
    }

    /**
     * @param list<Statement|FunctionCall> $history Identical call objects before creation, after creation and after removal
     */
    #[DataProvider('providerHistories')]
    public function testSameCallChangesOnlyWithTheStoredRoutineState(string $release, array $history): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
                $session->run('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (Statement|FunctionCall $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $history);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1305, null, null, null, 1305], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
