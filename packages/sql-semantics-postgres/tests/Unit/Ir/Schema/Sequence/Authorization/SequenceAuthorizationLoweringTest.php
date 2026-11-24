<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceAuthorizationLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceAuthorizationLowering::class)]
final class SequenceAuthorizationLoweringTest extends TestCase
{
    public function testLowerLeavesAbsentSourceActionsUnevaluated(): void
    {
        $semantics = new Semantics();
        $result = $semantics->analyze('ALTER SEQUENCE IF EXISTS missing OWNER TO unspecified, SET UNLOGGED', $semantics->context([]));

        self::assertInstanceOf(AlterSequenceAuthorization::class, $result);
        self::assertSame([], $result->actions);
        self::assertSame('ALTER SEQUENCE IF EXISTS "missing" OWNER TO CURRENT_USER', $result->toString());
    }
}
