<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\ImportTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ImportTable::class)]
#[Medium]
final class ImportTableTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Inspection())->inspect("IMPORT TABLE FROM 'a.sdi'");

        self::assertNull($operation->facts->output);
        self::assertSame([], $operation->declarations());
    }

    public function testRenderWritesTheFiles(): void
    {
        self::assertSame("IMPORT TABLE FROM 'a.sdi', 'b.sdi'", (new Inspection('mysql-8.0.44'))->inspect("import table from 'a.sdi', 'b.sdi'")->toString());
    }
}
