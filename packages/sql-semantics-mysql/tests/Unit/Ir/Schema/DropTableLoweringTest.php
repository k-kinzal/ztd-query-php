<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\DropTableLowering;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable as ParsedDrop;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropTableLowering::class)]
#[Medium]
final class DropTableLoweringTest extends TestCase
{
    public function testLowerResolvesAnAbsoluteTarget(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.a (value INTEGER)');
        $query = $inspection->inspect('DROP TABLE app.a', [$table]);
        self::assertInstanceOf(ParsedDrop::class, $query->statement);

        $drop = (new DropTableLowering())->lower($query->binding(), $query->statement);

        self::assertSame('app', $drop->targets[0]->name->schema->value);
        self::assertSame('a', $drop->targets[0]->name->table->value);
    }
}
