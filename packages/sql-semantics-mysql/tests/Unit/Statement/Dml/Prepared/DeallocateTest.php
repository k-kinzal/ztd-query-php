<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate;

#[CoversClass(Deallocate::class)]
#[Medium]
final class DeallocateTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DEALLOCATE PREPARE s');

        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesDeallocate(): void
    {
        self::assertSame('DEALLOCATE PREPARE s', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('drop prepare s')->toString());
    }
}
