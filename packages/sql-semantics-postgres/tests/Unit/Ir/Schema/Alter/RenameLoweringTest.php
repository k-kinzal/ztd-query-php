<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RenameLowering::class)]
#[Medium]
final class RenameLoweringTest extends TestCase
{
    public function testLowerDistinguishesAColumnPolicyFromTheTableNameChange(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.entries (id integer, name text)']);
        $table = $semantics->analyze('ALTER TABLE ONLY entries RENAME TO archived', $context);
        $column = $semantics->analyze('ALTER TABLE ONLY entries RENAME id TO "Id"', $context);

        self::assertInstanceOf(RenameTable::class, $table);
        self::assertSame('ALTER TABLE "public"."entries" RENAME TO "archived"', $table->toString());
        self::assertInstanceOf(RenameColumn::class, $column);
        self::assertTrue($column->only);
        self::assertSame(['id', 'Id'], [$column->column->value, $column->destination->value]);
    }
}
