<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(MoveSequenceLowering::class)]
final class MoveSequenceLoweringTest extends TestCase
{
    public function testLowerResolvesAnEmptyDeclaredDestination(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SCHEMA "store.棚"', 'CREATE SEQUENCE public.ticket']);
        $move = $semantics->analyze('ALTER SEQUENCE public.ticket SET SCHEMA "store.棚"', $context);

        self::assertInstanceOf(MoveSequence::class, $move);
        self::assertSame('store.棚', $move->destination?->value);
        self::assertSame('ALTER SEQUENCE "public"."ticket" SET SCHEMA "store.棚"', $move->toString());
    }

    public function testLowerDoesNotStoreTheUnevaluatedDestination(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket']);
        $move = $semantics->analyze('ALTER SEQUENCE IF EXISTS unknown.ticket SET SCHEMA "not declared"', $context);

        self::assertInstanceOf(MoveSequence::class, $move);
        self::assertNull($move->destination);
        self::assertSame('ALTER SEQUENCE IF EXISTS "unknown"."ticket" SET SCHEMA "pg_catalog"', $move->toString());
    }

    public function testLowerRejectsAMissingDestinationForThePresentSource(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket']);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence move requires a declared destination namespace.');

        $semantics->analyze('ALTER SEQUENCE IF EXISTS public.ticket SET SCHEMA unknown', $context);
    }
}
