<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Lookup;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Lookup\PrimaryKeys;
use MySqlMemory\Plan\Path\Source\KeyLookup;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Query;

#[CoversClass(PrimaryKeys::class)]
#[Small]
final class PrimaryKeysTest extends TestCase
{
    public function testPreparableLeavesRandomCallsForTheOrdinaryRowPredicate(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY); INSERT INTO t VALUES (1),(2),(3),(4),(5)');
        $result = $session->query('SELECT id,(SELECT 1 FROM t b WHERE b.id=1 AND RAND(17)>0.5) FROM t ORDER BY id')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1',null],['2','1'],['3',null],['4',null],['5','1']], $result->rows);
    }

    /**
     * @return iterable<string, array{string, class-string<KeyLookup|TableScan>}>
     */
    public static function providerPredicates(): iterable
    {
        yield 'complete key' => ['a=1 AND b=2', KeyLookup::class];
        yield 'reversed operands' => ['2=b AND (1=a)', KeyLookup::class];
        yield 'null-safe integer equality' => ['a<=>1 AND b<=>2', KeyLookup::class];
        yield 'partial key' => ['a=1', TableScan::class];
        yield 'alternative keys' => ['a=1 OR b=2', TableScan::class];
        yield 'string coercion' => ["a='1' AND b=2", TableScan::class];
        yield 'nonliteral expression' => ['a=ABS(1) AND b=2', TableScan::class];
    }

    /**
     * @param class-string<KeyLookup|TableScan> $expected
     */
    #[DataProvider('providerPredicates')]
    public function testPlanRequiresACompleteLiteralIntegerPrimaryKey(string $predicate, string $expected): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t (a INT,b INT,PRIMARY KEY(a,b))');
        $operation = $session->analyze('SELECT a,b FROM t WHERE ' . $predicate);
        self::assertInstanceOf(Query::class, $operation->statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $plan = $planner->query($operation->statement, null);

        self::assertInstanceOf(Project::class, $plan->root);
        self::assertInstanceOf(Filter::class, $plan->root->input);
        self::assertInstanceOf($expected, $plan->root->input->input);
    }

    public function testBindingLeavesNumericComparisonOfAStringKeyAsAMultirowRead(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query("CREATE TABLE t (a VARCHAR(3) PRIMARY KEY); INSERT INTO t VALUES ('1'),('01')");
        $result = $session->query('SELECT a FROM t WHERE a=1 ORDER BY a')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['01'], ['1']], $result->rows);
    }
}
