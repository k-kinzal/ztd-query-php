<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;

#[CoversClass(LessThan::class)]
#[Medium]
final class LessThanTest extends TestCase
{
    public function testDeriveBoundDerivesTheRow(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES LESS THAN (b))');

        self::assertSame('Column b does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesMaxvalueWithoutParentheses(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION p VALUES LESS THAN MAXVALUE)', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES LESS THAN MAXVALUE)')->toString());
    }
}
