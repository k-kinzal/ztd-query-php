<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Alter\DropColumnLowering;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(DropColumnLowering::class)]
#[Small]
final class DropColumnLoweringTest extends TestCase
{
    public function testLowerSelectsTheDeclaredMemberOfTheResolvedTemporaryTable(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE items (id INTEGER PRIMARY KEY, old INTEGER UNIQUE)',
            'CREATE TEMP TABLE items (id INTEGER PRIMARY KEY, keep INTEGER, OLD INTEGER CHECK (old>0), calculated AS (keep+1))',
        ]);
        $drop = $semantics->analyze('ALTER TABLE ITEMS DROP oLd', $context);

        self::assertInstanceOf(DropColumn::class, $drop);
        self::assertSame(2, $drop->slot);
        self::assertSame('OLD', $drop->column->value);
        self::assertFalse($drop->generated);
        self::assertSame('ALTER TABLE "temp"."items" DROP COLUMN "OLD"', $drop->toString());
    }
}
