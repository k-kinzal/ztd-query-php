<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Deallocate;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Deallocate::class)]
#[Medium]
final class DeallocateTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect('DEALLOCATE PREPARE s');

        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesDeallocate(): void
    {
        self::assertSame('DEALLOCATE PREPARE s', (new Inspection('mysql-5.6.51'))->inspect('drop prepare s')->toString());
    }
}
