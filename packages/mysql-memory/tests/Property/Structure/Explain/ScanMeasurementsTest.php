<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Explain;

use Fuzz\Structure\Audit\Explain\ScanMeasurements;
use Fuzz\Structure\Audit\Observations;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Enforces the narrow boundary of the physical scan-measurement exception.
 */
#[CoversNothing]
#[Small]
final class ScanMeasurementsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerReports(): iterable
    {
        yield 'valid estimate' => ["-> Table scan on t  (cost=0.35 rows=1) (actual time=0.1..0.2 rows=1 loops=1)\n", true];
        yield 'engineering notation' => ["-> Table scan on t  (cost=1e+2 rows=1) (actual time=934e-6..1e-2 rows=1 loops=1)\n", true];
        yield 'negative time' => ["-> Table scan on t  (cost=0.35 rows=1) (actual time=-1..0.2 rows=1 loops=1)\n", false];
        yield 'reversed time' => ["-> Table scan on t  (cost=0.35 rows=1) (actual time=2..1 rows=1 loops=1)\n", false];
        yield 'nonfinite estimate' => ["-> Table scan on t  (cost=1e999 rows=1) (actual time=0.1..0.2 rows=1 loops=1)\n", false];
        yield 'wrong count' => ["-> Table scan on t  (cost=0.35 rows=1) (actual time=0.1..0.2 rows=2 loops=1)\n", false];
        yield 'wrong loops' => ["-> Table scan on t  (cost=0.35 rows=1) (actual time=0.1..0.2 rows=1 loops=2)\n", false];
        yield 'wrong table' => ["-> Table scan on u  (cost=0.35 rows=1) (actual time=0.1..0.2 rows=1 loops=1)\n", false];
        yield 'missing measurements' => ["-> Table scan on t\n", false];
    }

    /**
     * Preserves counts and report structure while accepting validated physical quantities.
     */
    #[DataProvider('providerReports')]
    public function testComparableRequiresACompleteValidMeasurement(string $text, bool $matches): void
    {
        $node = new M\Utility\Explain\Explain(new M\QueryRoot(new M\Query\ExplicitTable(new QualifiedName(new Name('t')))), analyze: true);
        $native = ['results' => [['columns' => [['name' => 'EXPLAIN', 'type' => 253]], 'rows' => [[$text]]]], 'warnings' => []];
        $memory = $native;
        $memory['results'][0]['rows'] = [["-> Table scan on t  (cost=0 rows=1) (actual time=1..2 rows=1 loops=1)\n"]];
        self::assertSame($matches, (new Observations())->compare($node, '8.4.7', $native, $memory)['matches']);
        self::assertSame($text, $native['results'][0]['rows'][0][0]);
        $memory['warnings'] = [['Warning', '1000', 'unexpected']];
        self::assertFalse((new Observations())->compare($node, '8.4.7', $native, $memory)['matches']);
    }

    /**
     * Requires the memory report's zero cost and own count estimate.
     */
    public function testTextKeepsMemoryEstimatesAndMetadataConstrained(): void
    {
        $contract = new ScanMeasurements();
        self::assertNull($contract->text("-> Table scan on t  (cost=1 rows=1) (actual time=1..2 rows=1 loops=1)\n", 't', true));
        self::assertNull($contract->text("-> Table scan on t  (cost=0 rows=2) (actual time=1..2 rows=1 loops=1)\n", 't', true));
        $node = new M\Utility\Explain\Explain(new M\QueryRoot(new M\Query\ExplicitTable(new QualifiedName(new Name('t')))), analyze: true);
        $left = ['results' => [['columns' => [['name' => 'EXPLAIN', 'type' => 253]], 'rows' => [["-> Table scan on t  (cost=0 rows=1) (actual time=1..2 rows=1 loops=1)\n"]]]], 'warnings' => []];
        $right = $left;
        $right['results'][0]['columns'][0]['type'] = 252;
        self::assertFalse((new Observations())->compare($node, '8.4.7', $left, $right)['matches']);
    }
}
