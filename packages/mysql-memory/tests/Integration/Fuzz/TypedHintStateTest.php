<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Comparison;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\SessionSetup;
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
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ResourceGroupHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TypedHintStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<QueryRoot>}>
     */
    public static function providerStructures(): iterable
    {
        $nodes = [];
        $precision = new VariableHint('div_precision_increment', new HintLiteral(HintLiteralKind::Integer, '10'));
        $group = new ResourceGroupHint('missing');
        $hints = [[$group], [new VariableHint('sort_buffer_size', new HintLiteral(HintLiteralKind::Integer, '1')), $group], [$precision], [$precision, new VariableHint('div_precision_increment', new HintLiteral(HintLiteralKind::Integer, '3'))]];
        foreach ($hints as $hintList) {
            foreach ([null, new TableReference(new QualifiedName(new Name('t')))] as $from) {
                foreach ([new NumberLiteral('1'), new Parameter()] as $left) {
                    $nodes[] = new QueryRoot(new Select([], [new SelectExpression(new Arithmetic(ArithmeticOperator::Divide, $left, new NumberLiteral('3')))], $from, hints: $hintList));
                }
            }
        }
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<QueryRoot> $nodes Original objects reused through all three states
     */
    #[DataProvider('providerStructures')]
    public function testTypedHintsSurviveDatabaseAndTableTransitions(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET sql_mode='', collation_connection='utf8mb4_bin'");
                $session = (new Instance($release))->connect();
                (new SessionSetup())->apply($session, '');
                $observed = array_map(static function (array $setup) use ($native, $session, $nodes): array {
                    foreach ($setup as $sql) {
                        $native->query($sql);
                        $session->query($sql);
                    }
                    $session->text = 'unrelated invalid input';
                    return array_map(static function (QueryRoot $node) use ($native, $session): array {
                        $bindings = (new Comparison())->parameters($node, $session);
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile(), '?'), count($bindings));
                        $actual = (new Memory())->observe($session, $node, $bindings);
                        return [serialize($expected), serialize($actual)];
                    }, $nodes);
                }, [[], ['CREATE DATABASE d', 'USE d'], ['CREATE TABLE t(a INT)', 'INSERT INTO t VALUES(1),(2)']]);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame(4, $session->variables->read('div_precision_increment'));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
