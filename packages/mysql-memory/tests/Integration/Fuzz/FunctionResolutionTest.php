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
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FunctionResolutionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<FunctionCall>, list<CreateFunction>, list<?int>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $calls = [];
            $definitions = [];
            $errors = [];
            foreach (['COLLATION', 'ORDER', 'foo', 'window', 'ABS', '_utf8mb4'] as $name) {
                $calls[] = new FunctionCall(new Name($name), [new CallArgument(new NumberLiteral('3'))]);
                $calls[] = new FunctionCall(new Name($name), [new CallArgument(new NumberLiteral('3'))], new Name('d'));
                $type = new Integral(IntegralKind::Int);
                $definitions[] = new CreateFunction(new QualifiedName(new Name($name), new Name('d')), new ParameterList([new Parameter(new Name('x'), $type)]), new Integral(IntegralKind::Int), new \SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation(new ReturnStatement(new NumberLiteral('7'))));
                $missing = match ($name) {
                    'COLLATION', 'ORDER' => 1630,
                    'window' => str_starts_with($release, '5.') ? 1305 : 1630,
                    'ABS' => null,
                    'foo', '_utf8mb4' => 1305,
                };
                $errors[] = $missing;
                $errors[] = $missing ?? 1305;
            }
            yield $release => [$release, $calls, $definitions, $errors];
        }
    }

    /**
     * @param list<FunctionCall> $calls The original call objects reused before and after definitions
     * @param list<CreateFunction> $definitions Stored functions returning 7; native ABS still returns 3
     * @param list<?int> $errors The expected missing-function distinctions
     */
    #[DataProvider('providerStructures')]
    public function testSameCallsResolveAgainstNewDefinitionsWithoutChangingNativePrecedence(string $release, array $calls, array $definitions, array $errors): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci', 'CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', 'USE d', 'SET GLOBAL log_bin_trust_function_creators=1'];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $profile = $session->semantics()->profile();
                $expected = static fn (Statement|Scalar $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile)));
                $observe = static function (Statement|Scalar $node) use ($session): array {
                    $result = (new Memory())->observe($session, $node);

                    return [serialize($result), $result['error'][0] ?? null];
                };
                $session->text = 'unrelated invalid input';
                $before = array_map($observe, $calls);
                self::assertSame(array_map($expected, $calls), array_column($before, 0));
                self::assertSame($errors, array_column($before, 1));
                self::assertSame(array_map($expected, $definitions), array_column(array_map($observe, $definitions), 0));
                $expectedAfter = array_map($expected, [...$calls, ...$calls]);
                $after = array_map($observe, [...$calls, ...$calls]);

                self::assertSame($expectedAfter, array_column($after, 0));
                self::assertSame(array_fill(0, count($after), null), array_column($after, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
