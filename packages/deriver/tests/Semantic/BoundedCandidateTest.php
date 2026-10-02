<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Project\EntryPoint;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ValueQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Candidates;

/**
 * Exhausted budgets keep exact candidates and an explicit residual for the rest, instead of dropping statements.
 */
#[CoversNothing]
#[Medium]
final class BoundedCandidateTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testBranchesBeyondThePartitionBudgetKeepAResidual(): void
    {
        $result = Candidates::sink('function target($a,$b,$c,$d,$e,$f){sink(($a?"a":"A").($b?"b":"B").($c?"c":"C").($d?"d":"D").($e?"e":"E").($f?"f":"F"));}');
        self::assertCount(32, $result->normalOutcomes);
        self::assertCount(31, array_filter(Candidates::values($result), static fn (Term $value): bool => $value->isConcrete()));
        foreach (Candidates::choices(['a', 'b', 'c', 'd', 'e', 'f']) as $sql) {
            self::assertTrue(Candidates::contained($result, $sql), $sql);
        }
        self::assertContains('BUDGET_EXCEEDED', array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testObservationsBeyondTheBudgetKeepTheirSharedPrefix(): void
    {
        $result = Candidates::sink('function target(){foreach([' . implode(',', range(1, 40)) . '] as $id){sink("SELECT * FROM t WHERE id = $id");}}');
        self::assertCount(32, $result->normalOutcomes);
        foreach (range(1, 40) as $id) {
            self::assertTrue(Candidates::contained($result, 'SELECT * FROM t WHERE id = ' . $id), (string) $id);
        }
        $residual = array_values(array_filter(Candidates::values($result), static fn (Term $value): bool => !$value->isConcrete()));
        self::assertCount(1, $residual);
        self::assertSame('SELECT * FROM t WHERE id = ', $residual[0]->operands[0]->native());
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testUnknownLoopsKeepTheirExactUnrollingsAndPrefix(): void
    {
        $result = Candidates::sink('function target(array $xs){$s="SELECT * FROM t WHERE 1";foreach($xs as $x){$s.=" AND c = ?";}sink($s);}');
        self::assertSame('closed', $result->assessment->closure);
        foreach (range(0, 40) as $count) {
            self::assertTrue(Candidates::contained($result, 'SELECT * FROM t WHERE 1' . str_repeat(' AND c = ?', $count)), (string) $count);
        }
        self::assertFalse(Candidates::contained($result, 'SELECT * FROM u'));
        self::assertContains('SELECT * FROM t WHERE 1 AND c = ? AND c = ?', array_map(static fn (Term $value): string|int|float|bool|null => $value->isConcrete() ? $value->literal : null, Candidates::values($result)));
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testRecursiveBuildersTerminateWithBaseAndUnrolledCandidates(): void
    {
        $started = hrtime(true);
        $result = Candidates::sink('function where(array $c): string { if (!$c) return "1=1"; $k = array_shift($c); return "$k = ? AND " . where($c); } function target(array $c){ sink("SELECT * FROM t WHERE " . where($c)); }');
        self::assertLessThan(10.0, (hrtime(true) - $started) / 1e9);
        self::assertTrue(Candidates::contained($result, 'SELECT * FROM t WHERE 1=1'));
        self::assertContains('recursive-specialization', array_column($result->frontiers, 'operation'));
        $prefixes = array_map(static fn (Term $value): string => (new \Deriver\Value\StringPrefix())->known($value)[0], Candidates::values($result));
        self::assertContains('SELECT * FROM t WHERE ', $prefixes);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testSymbolicRecursionBoundKeepsUnrelatedCallerState(): void
    {
        $session = Analysis::session('<?php function where(array $c): string { if (!$c) return "1=1"; $k = array_shift($c); return "$k = ? AND " . where($c); } function target(PDO $pdo, array $c){ $sql = "SELECT 1"; $w = where($c); $pdo->query($sql); }');
        $call = $session->callsTo('query')[0];
        self::assertNotNull($call->receiver);
        foreach ($session->derive(new ValueQuery($call->receiver))->normalOutcomes as $outcome) {
            self::assertSame(['parameter', 'PDO'], [$outcome->values['value']->kind, $outcome->values['value']->attributes['type'] ?? null]);
        }
        foreach ($session->derive(new ValueQuery($call->argument(0)))->normalOutcomes as $outcome) {
            self::assertSame('SELECT 1', $outcome->values['value']->native());
        }
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testConcreteRecursionIsNotLimitedBySymbolicRecursion(): void
    {
        $session = Analysis::session('<?php function sink($sql){} function where(array $c): string { if (!$c) return "1=1"; $k = array_shift($c); return "$k = ? AND " . where($c); } function target(){ sink(where(["a","b","c","d","e","f"])); }');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0), budget: new Budget(symbolicRecursion: 1)));
        self::assertSame(['a = ? AND b = ? AND c = ? AND d = ? AND e = ? AND f = ? AND 1=1'], array_map(static fn (Term $value): string|int|float|bool|null => $value->isConcrete() ? $value->literal : null, Candidates::values($result)));
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testEntrypointCallersBeyondTheBudgetKeepAResidual(): void
    {
        $calls = implode('', array_map(static fn (int $id): string => 'find(' . $id . ');', range(1, 33)));
        $session = Analysis::session('<?php function sink($sql){} function find(int $id){ sink("SELECT * FROM t WHERE id = " . $id); } function main(){' . $calls . '}');
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0), scope: QueryScope::fromEntrypoints([new EntryPoint('main')])));
        self::assertCount(32, $result->normalOutcomes);
        foreach (range(1, 33) as $id) {
            self::assertTrue(Candidates::contained($result, 'SELECT * FROM t WHERE id = ' . $id), (string) $id);
        }
    }

    /**
     * @param string $source Fixture calling sink() once
     * @param int $partitions Partition budget
     * @param list<string|int> $runtime Values PHP 8.3 passes to sink() for some inputs
     * @throws JsonException If captured fixture values cannot be encoded
     */
    #[DataProvider('joins')]
    public function testJoinedPathsKeepEveryRuntimeValue(string $source, int $partitions, array $runtime): void
    {
        $session = Analysis::session('<?php function sink($sql){} ' . $source);
        $result = $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0), budget: new Budget(partitions: $partitions)));
        self::assertSame($runtime, array_values(array_filter($runtime, static fn (string|int $value): bool => Candidates::contained($result, $value))));
    }

    /**
     * @return iterable<string, array{string, int, list<string|int>}>
     */
    public static function joins(): iterable
    {
        $appends = 'function target(bool $a, bool $b, bool $c, bool $d, bool $e, bool $f1, bool $f3) { $v = 0; $arr = []; if ($a) { $arr[] = 0; } if ($b) { $arr[] = 0; } if ($c) { $arr[] = 0; } if ($d) { $arr[] = 0; } if ($e) { $arr[] = 0; } $arr[] = 0; if ($f1) { $v = 9; } else { if ($f3) { $v = 5; } else { $v = 1; } } sink($v); }';
        $property = 'class C { public int $p; } function target(bool $a, bool $b, bool $k) { $o = new C(); if ($k) { $o->p = 5; } if ($a) { $x = 1; } else { $x = 2; } if ($b) { $y = 1; } else { $y = 2; } try { $r = $o->p; } catch (Error $e) { $r = "err"; } sink($r); }';
        $loop = 'function target(bool $flag, int $n, array $xs) { $s = "none"; if ($flag) { $mode = 1; } else { $mode = 1; } for ($i = 0; $i < $n; $i++) { if ($xs[$i]) { $t = 1; } else { $t = 2; } if (!$flag && $i > 40) { $s = "X"; } } sink($s); }';
        yield 'appends to one array before later branches' => [$appends, 32, [1, 5, 9]];
        yield 'appends with a small budget' => [$appends, 2, [1, 5, 9]];
        yield 'typed property that may be uninitialized' => [$property, 2, [5, 'err']];
        yield 'loop guard of one joined path' => [$loop, 16, ['none', 'X']];
        yield 'loop guard with a small budget' => [$loop, 2, ['none', 'X']];
    }
}
