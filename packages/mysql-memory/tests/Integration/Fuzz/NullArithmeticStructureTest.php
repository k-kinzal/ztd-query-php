<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Closure;
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
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Scalar;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class NullArithmeticStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Scalar>}>
     */
    public static function providerStructures(): iterable
    {
        $sources = [
            static fn (): Scalar => new NullLiteral(),
            static fn (): Scalar => new Parameter('?'),
            static fn (): Scalar => new NumberLiteral('1'),
            static fn (): Scalar => new NumberLiteral('1.5'),
            static fn (): Scalar => new NumberLiteral('1e0'),
            static fn (): Scalar => new StringLiteral(['1']),
            static fn (): Scalar => new NumberLiteral('12.34'),
            static fn (): Scalar => new NumberLiteral('123456'),
            static fn (): Scalar => new StringLiteral(['123456']),
        ];
        $statements = [
            new Parameter('?'),
            new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('COERCIBILITY'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new Parameter('?'))]),
            new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Invert, new Parameter('?')),
        ];
        foreach ([ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::Multiply, ArithmeticOperator::Divide, ArithmeticOperator::Modulo, ArithmeticOperator::IntegerDivide] as $operator) {
            array_push($statements, ...array_map(static fn (Closure $source): Arithmetic => new Arithmetic($operator, new NullLiteral(), $source()), $sources));
            array_push($statements, ...array_map(static fn (Closure $source): Arithmetic => new Arithmetic($operator, $source(), new NullLiteral()), $sources));
        }
        $quotients = [
            static fn (): Scalar => new NumberLiteral('1'),
            static fn (): Scalar => new NumberLiteral('1.23'),
            static fn (): Scalar => new NumberLiteral('12.34'),
            static fn (): Scalar => new StringLiteral(['12']),
            static fn (): Scalar => new StringLiteral(['123']),
        ];
        foreach ($quotients as $left) {
            array_push($statements, ...array_map(static fn (Closure $right): Arithmetic => new Arithmetic(ArithmeticOperator::IntegerDivide, $left(), $right()), $quotients));
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<Scalar> $statements NULL on either side of each arithmetic operator, including a bound NULL marker
     */
    #[DataProvider('providerStructures')]
    public function testSemanticArithmeticRetainsNullOperandMetadata(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        $server = \MySqlMemory\Server\Server::start($release);
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $wire = new mysqli($server->host, 'root', '', port: $server->port);
            try {
                $wire->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $wire->query("SET sql_mode='', timestamp=1700000000");
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $native->query("SET sql_mode='', timestamp=1700000000");
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci; SET sql_mode='', timestamp=1700000000");
                $observed = array_map(static function (Scalar $node) use ($native, $session, $wire): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile(), '?'), count((new \Fuzz\Structure\Bindings())->nulls($node)));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node, (new \Fuzz\Structure\Audit\Comparison())->parameters($node, $session));
                    $protocol = (new Native())->observe($wire, (new Reference())->sql($node, $session->semantics()->profile(), '?'), count((new \Fuzz\Structure\Bindings())->nulls($node)));

                    return [serialize($expected), serialize($actual), serialize($protocol)];
                }, $statements);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame(array_column($observed, 0), array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
                $wire->close();
            }
        } finally {
            $server->stop();
            $container->stop();
        }
    }
}
