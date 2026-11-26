<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Bound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesIn;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ValuesIn::class)]
#[Medium]
final class ValuesInTest extends TestCase
{
    public function testDeriveBoundDerivesTheValues(): void
    {
        $alter = (new Inspection())->diagnose('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1, b))');

        self::assertCount(1, $alter->facts->diagnostics);
    }

    public function testRenderWritesOneRow(): void
    {
        self::assertSame('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1, 2))', (new Inspection('mysql-5.7.44'))->diagnose('ALTER TABLE t ADD PARTITION (PARTITION p VALUES IN (1, 2))')->toString());
    }
}
