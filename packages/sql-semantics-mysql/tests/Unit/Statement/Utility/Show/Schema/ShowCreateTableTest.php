<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;

#[CoversClass(ShowCreateTable::class)]
#[Medium]
final class ShowCreateTableTest extends TestCase
{
    public function testDeriveStatementLeavesTheShapeOpen(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TABLE t');
        self::assertInstanceOf(ShowCreateTable::class, $show->statement);
        $missing = (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TABLE t', []);
        self::assertSame('Relation t does not exist.', $missing->facts->diagnostics[0]->message());
        self::assertFalse($show->shape()?->complete());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE TABLE t', (new Semantics(Dialect::MySql))->analyze('SHOW CREATE TABLE t')->toString());
    }
}
