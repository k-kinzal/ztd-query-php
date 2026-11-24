<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(ReconfigureSequenceLowering::class)]
final class ReconfigureSequenceLoweringTest extends TestCase
{
    public function testLowerSkipsUnevaluatedOwnershipAndIdentityOperandsOnAbsence(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze('ALTER SEQUENCE IF EXISTS missing.ticket OWNED BY nonexistent.id SEQUENCE NAME unrelated', $semantics->context([]));

        self::assertInstanceOf(ReconfigureSequence::class, $value);
        self::assertNull($value->changes);
        self::assertSame('ALTER SEQUENCE IF EXISTS "missing"."ticket" RESTART', $value->toString());
    }
}
