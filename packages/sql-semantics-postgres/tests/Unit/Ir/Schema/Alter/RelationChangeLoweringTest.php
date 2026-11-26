<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RelationChangeLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTable;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RelationChangeLowering::class)]
#[Medium]
final class RelationChangeLoweringTest extends TestCase
{
    public function testLowerRoutesRenamesAndRemovalsToTheirOwners(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE public.report (id integer)']);

        self::assertInstanceOf(DropTable::class, $semantics->analyze('DROP TABLE report', $context));
        self::assertInstanceOf(RenameTable::class, $semantics->analyze('ALTER TABLE report RENAME TO archived', $context));
    }
}
