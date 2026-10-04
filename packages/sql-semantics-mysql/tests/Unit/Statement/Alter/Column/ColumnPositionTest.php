<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition;

#[CoversClass(ColumnPosition::class)]
#[Medium]
final class ColumnPositionTest extends TestCase
{
    public function testRenderWritesFirstOrAfter(): void
    {
        self::assertSame('ALTER TABLE t ADD COLUMN a INT FIRST, ADD COLUMN b INT AFTER a', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD a INT FIRST, ADD b INT AFTER a')->toString());
    }
}
