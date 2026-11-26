<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameTargetLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(RenameTargetLowering::class)]
#[Medium]
final class RenameTargetLoweringTest extends TestCase
{
    public function testLowerPreservesKnownAbsenceInTheNamedSchema(): void
    {
        $semantics = new Semantics();
        $result = $semantics->analyze('ALTER TABLE IF EXISTS public.absent RENAME TO stored', $semantics->context([]));

        self::assertInstanceOf(RenameTable::class, $result);
        self::assertTrue($result->ifExists);
        self::assertSame('ALTER TABLE IF EXISTS "public"."absent" RENAME TO "stored"', $result->toString());
    }
}
