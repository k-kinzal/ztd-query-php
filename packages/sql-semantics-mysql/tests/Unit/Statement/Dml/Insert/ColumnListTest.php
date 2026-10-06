<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList;

#[CoversClass(ColumnList::class)]
#[Medium]
final class ColumnListTest extends TestCase
{
    public function testRenderWritesTheColumns(): void
    {
        self::assertSame('INSERT INTO t (a, t.b) VALUES (1, 2)', (new Semantics(Dialect::MySql))->analyze('insert t (a, t.b) values (1, 2)')->toString());
        self::assertSame('INSERT INTO t () VALUES ()', (new Semantics(Dialect::MySql))->analyze('insert t () values ()')->toString());
    }
}
