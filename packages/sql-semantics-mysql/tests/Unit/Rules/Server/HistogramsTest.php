<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Server\Histograms;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn;

#[CoversClass(Histograms::class)]
#[Medium]
final class HistogramsTest extends TestCase
{
    public function testDeriveReportsAnUnknownColumn(): void
    {
        $semantics = new Semantics();
        $table = $semantics->inspect('CREATE TABLE t (a INT)');
        $analyze = $semantics->inspect('ANALYZE TABLE t UPDATE HISTOGRAM ON a, b', [$table]);

        self::assertCount(1, $analyze->facts->diagnostics);
        self::assertInstanceOf(UnknownHistogramColumn::class, $analyze->facts->diagnostics[0]);
    }

    public function testDeriveAcceptsAnUndeclaredTable(): void
    {
        self::assertSame([], (new Semantics())->inspect('ANALYZE TABLE u DROP HISTOGRAM ON x')->facts->diagnostics);
    }

    public function testBucketsTellsWhetherTheCountIsInRange(): void
    {
        $histograms = new Histograms();

        self::assertSame([false, true, true, false], [$histograms->buckets(new Numeral('0')), $histograms->buckets(new Numeral('1')), $histograms->buckets(new Numeral('01024')), $histograms->buckets(new Numeral('1025'))]);
    }

    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('ANALYZE TABLE t DROP HISTOGRAM ON `a b`, c', (new Semantics())->inspect('analyze table t drop histogram on `a b`, c')->toString());
    }
}
