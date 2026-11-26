<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTableLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Testing\PostgreSql\Inspection;

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

        self::assertSame('app', $drop->targets[0]->schema->value);
        self::assertSame('a', $drop->targets[0]->table->value);
    }
}
