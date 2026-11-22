<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Subpartitioning;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Subpartitioning::class)]
#[Medium]
final class SubpartitioningTest extends TestCase
{
    public function testDeriveSubpartitioningDerivesTheMethod(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');

        self::assertSame('Column b does not exist in the table.', $semantics->diagnose('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY KEY (b)', [$table])->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheMethodAndTheCount(): void
    {
        self::assertSame('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (a) SUBPARTITIONS 2', (new Inspection())->diagnose('ALTER TABLE t PARTITION BY RANGE (a) SUBPARTITION BY LINEAR HASH (a) SUBPARTITIONS 2')->toString());
    }
}
