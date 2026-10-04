<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesIn;

#[CoversClass(ValuesIn::class)]
#[Medium]
final class ValuesInTest extends TestCase
{
    public function testDeriveBoundDerivesTheValues(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1, b))');

        self::assertCount(1, $alter->facts->diagnostics);
    }

    public function testRenderWritesOneRow(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1, 2))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1, 2))')->toString());
    }
}
