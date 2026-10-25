<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;

#[CoversClass(BulkOptions::class)]
#[Medium]
final class BulkOptionsTest extends TestCase
{
    public function testRejectsAFractionalParallelism(): void
    {
        $this->expectExceptionMessage('LOAD parallelism is a decimal integer token no greater than 2147483647.');

        new BulkOptions(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.5'));
    }

    public function testRenderWritesTheOptions(): void
    {
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE t PARALLEL = 4 MEMORY = 10M ALGORITHM = BULK", (new Semantics(Dialect::MySql))->analyze("load data infile 'f' into table t parallel = 4 memory = 10M algorithm = bulk")->toString());
    }

    public function testRenderRejectsNoOption(): void
    {
        $this->expectExceptionMessage('Bulk options hold at least one option.');

        new BulkOptions();
    }
}
