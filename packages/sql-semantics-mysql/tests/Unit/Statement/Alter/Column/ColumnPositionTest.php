<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ColumnPosition;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ColumnPosition::class)]
#[Medium]
final class ColumnPositionTest extends TestCase
{
    public function testRenderWritesFirstOrAfter(): void
    {
        self::assertSame('ALTER TABLE t ADD COLUMN a INT FIRST, ADD COLUMN b INT AFTER a', (new Inspection())->inspect('ALTER TABLE t ADD a INT FIRST, ADD b INT AFTER a')->toString());
    }
}
