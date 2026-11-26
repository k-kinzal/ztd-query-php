<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AbsentTable::class)]
#[Medium]
final class AbsentTableTest extends TestCase
{
    public function testAMissingTableUnderIfExistsIsAbsent(): void
    {
        $drop = (new Inspection())->inspect('DROP TABLE IF EXISTS t', []);

        self::assertInstanceOf(DropTable::class, $drop->statement);
        self::assertInstanceOf(AbsentTable::class, $drop->facts->relation($drop->statement->tables[0])->table);
        self::assertSame([], $drop->facts->diagnostics);
    }
}
