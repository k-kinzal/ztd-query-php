<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableKey;

#[CoversClass(TableKey::class)]
#[Small]
final class TableKeyTest extends TestCase
{
    public function testColumnsAndRowidDescribeAnEstablishedKey(): void
    {
        $key = new TableKey([1, 0], null, 2, true);

        self::assertSame([1, 0], $key->columns);
        self::assertNull($key->rowid);
        self::assertSame(2, $key->constraints);
        self::assertTrue($key->autoincrement);
    }

    public function testConstraintsIsZeroForATableWithoutPrimaryKey(): void
    {
        $key = new TableKey();

        self::assertSame([], $key->columns);
        self::assertSame(0, $key->constraints);
        self::assertFalse($key->autoincrement);
    }
}
