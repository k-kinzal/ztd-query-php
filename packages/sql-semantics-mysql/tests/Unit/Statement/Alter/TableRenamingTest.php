<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\TableRenaming;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableRenaming::class)]
#[Medium]
final class TableRenamingTest extends TestCase
{
    public function testRenderWritesBothNames(): void
    {
        self::assertSame('RENAME TABLE db.t TO archive.t', (new Inspection())->inspect('RENAME TABLE db.t TO archive.t')->toString());
    }
}
