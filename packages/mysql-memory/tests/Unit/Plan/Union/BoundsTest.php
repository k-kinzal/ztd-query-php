<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Union;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\MySqlMemory\Plan\Union\Bounds::class)]
#[Small]
final class BoundsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<list<string>>, int, int}>
     */
    public static function providerBounds(): iterable
    {
        yield '5.6 materializes the first operand at zero limit' => ['5.6.51', 'ALL SELECT @x:=@x+n FROM t WHERE n>=2 LIMIT 0', [], 3, 5];
        yield '5.6 bounds only the last operand' => ['5.6.51', 'ALL SELECT @x:=@x+n FROM t WHERE n>=2 LIMIT 1 OFFSET 1', [['3']], 5, 9];
        yield '5.7 skips projection for offset rows' => ['5.7.44', 'ALL SELECT @x:=@x+n FROM t WHERE n>=2 LIMIT 1 OFFSET 1', [['2']], 2, 2];
        yield '5.7 fully materializes distinct operands' => ['5.7.44', 'SELECT @x:=@x+n FROM t WHERE n>=2 LIMIT 0', [], 6, 12];
    }

    /**
     * @param list<list<string>> $rows The rows returned within the bounds
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerBounds')]
    public function testPlanPreservesNativeOperandEffectsAndRowCounts(string $version, string $tail, array $rows, int $found, int $assigned): void
    {
        $session = (new \MySqlMemory\Instance($version))->connect();
        $session->query('USE mysql; CREATE TABLE t(n INT); INSERT INTO t VALUES(1),(2),(2),(3); SET @x=0');
        $result = $session->query('SELECT @x:=@x+n AS v FROM t WHERE n<=2 UNION ' . $tail)[0];

        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame($rows, $result->rows);
        self::assertSame($found, $session->variables->foundRows);
        self::assertSame($assigned, $session->variables->user('x')[0]);
    }

    public function testCombinedRecognizesAUnionUnderGlobalSorting(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51'))->connect();
        $operation = $session->analyze('SELECT 1');
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new \MySqlMemory\Plan\Planner($operation, $operation->statementInformation(), $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context), $session->instance->dictionary);
        $rules = new \MySqlMemory\Plan\Union\Bounds($planner);
        $leaf = new \MySqlMemory\Plan\Path\Source\SingleRow();
        $union = new \MySqlMemory\Plan\Path\Combine\SetOperation(\MySqlMemory\Plan\Path\SetKind::Union, false, $leaf, $leaf, []);

        self::assertTrue($rules->combined(new \MySqlMemory\Plan\Path\Transform\Sort($union, [])));
        self::assertFalse($rules->combined($leaf));
    }

    public function testStreamedRejectsDistinctPrefixesAndGlobalSorting(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51'))->connect();
        $operation = $session->analyze('SELECT 1');
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new \MySqlMemory\Plan\Planner($operation, $operation->statementInformation(), $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context), $session->instance->dictionary);
        $rules = new \MySqlMemory\Plan\Union\Bounds($planner);
        $leaf = new \MySqlMemory\Plan\Path\Source\SingleRow();
        $distinct = new \MySqlMemory\Plan\Path\Combine\SetOperation(\MySqlMemory\Plan\Path\SetKind::Union, true, $leaf, $leaf, []);
        $all = new \MySqlMemory\Plan\Path\Combine\SetOperation(\MySqlMemory\Plan\Path\SetKind::Union, false, $leaf, $leaf, []);

        self::assertTrue($rules->streamed($all));
        self::assertFalse($rules->streamed(new \MySqlMemory\Plan\Path\Transform\Sort($all, [])));
        self::assertFalse($rules->streamed(new \MySqlMemory\Plan\Path\Combine\SetOperation(\MySqlMemory\Plan\Path\SetKind::Union, false, $distinct, $leaf, [])));
    }

    public function testBufferedPreservesStoredCountsWithoutBoundingSortedOperands(): void
    {
        $session = (new \MySqlMemory\Instance('5.6.51'))->connect();
        $operation = $session->analyze('SELECT 1');
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new \MySqlMemory\Plan\Planner($operation, $operation->statementInformation(), $session->settings(), new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context), $session->instance->dictionary);
        $rules = new \MySqlMemory\Plan\Union\Bounds($planner);
        $leaf = new \MySqlMemory\Plan\Path\Source\SingleRow();
        $union = new \MySqlMemory\Plan\Path\Combine\SetOperation(\MySqlMemory\Plan\Path\SetKind::Union, true, $leaf, $leaf, []);
        $result = $rules->buffered($union, false, 0, false);

        self::assertInstanceOf(\MySqlMemory\Plan\Path\Combine\SetOperation::class, $result);
        self::assertSame(\MySqlMemory\Plan\Path\SetCount::Materialized, $result->count);
        self::assertSame($leaf, $result->right);
    }

}
