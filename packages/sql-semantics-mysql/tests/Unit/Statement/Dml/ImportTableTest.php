<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\ImportTable;

#[CoversClass(ImportTable::class)]
#[Medium]
final class ImportTableTest extends TestCase
{
    public function testRejectsABinaryLiteralAsAFileName(): void
    {
        $this->expectExceptionMessage('An imported file name is a quoted string.');

        new ImportTable([new \SqlSemantics\Platform\MySql\Statement\Literal\Text('61', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal)]);
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("IMPORT TABLE FROM 'a.sdi'");

        self::assertNull($operation->facts->output);
        self::assertSame([], $operation->declarations());
    }

    public function testRenderWritesTheFiles(): void
    {
        self::assertSame("IMPORT TABLE FROM 'a.sdi', 'b.sdi'", (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("import table from 'a.sdi', 'b.sdi'")->toString());
    }
}
