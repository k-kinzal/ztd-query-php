<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\LoadRendering;

#[CoversClass(LoadRendering::class)]
#[Medium]
final class LoadRenderingTest extends TestCase
{
    public function testRenderPreservesOrdinaryInputAndAssignmentClauses(): void
    {
        $sql = "LOAD DATA LOCAL INFILE 'f' REPLACE INTO TABLE t COLUMNS TERMINATED BY ',' IGNORE 1 LINES (a, @b) SET a = @b";
        self::assertSame($sql, (new Semantics(Dialect::MySql, '8.4.7'))->analyze($sql)->toString());
    }
}
