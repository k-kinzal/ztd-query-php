<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\TargetTable;

#[CoversClass(TargetTable::class)]
#[Medium]
final class TargetTableTest extends TestCase
{
    public function testRenderWritesTheQualifiedName(): void
    {
        self::assertSame('DROP TABLE db.t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('DROP TABLE db.t')->toString());
    }
}
