<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLoggingLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SetSequenceLoggingLowering::class)]
final class SetSequenceLoggingLoweringTest extends TestCase
{
    public function testLowerRetainsTheOrderedActionsOnTheDeclaredSequence(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket']);
        $change = $semantics->analyze('ALTER SEQUENCE ticket SET LOGGED, SET UNLOGGED', $context);

        self::assertInstanceOf(SetSequenceLogging::class, $change);
        self::assertSame([SequenceLogging::Logged, SequenceLogging::Unlogged], $change->modes);
        self::assertSame('ALTER SEQUENCE "public"."ticket" SET LOGGED, SET UNLOGGED', $change->toString());
    }

    public function testLowerPublishesGuardedAbsenceWithoutTheUnevaluatedAssignments(): void
    {
        $semantics = new Semantics();
        $change = $semantics->analyze('ALTER SEQUENCE IF EXISTS absent SET UNLOGGED, SET LOGGED', $semantics->context([]));

        self::assertInstanceOf(SetSequenceLogging::class, $change);
        self::assertSame([], $change->modes);
        self::assertSame('ALTER SEQUENCE IF EXISTS "absent" SET LOGGED', $change->toString());
    }
}
