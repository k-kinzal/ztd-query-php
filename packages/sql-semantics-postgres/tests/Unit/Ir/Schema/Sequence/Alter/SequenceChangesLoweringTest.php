<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChangesLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceChangesLowering::class)]
final class SequenceChangesLoweringTest extends TestCase
{
    public function testLowerReordersAssignmentsAndKeepsTheMeaningOfImplicitRestart(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket', 'CREATE TABLE public.receiver (id bigint)']);
        $value = $semantics->analyze('ALTER SEQUENCE ticket RESTART OWNED BY receiver.id START +0005', $context);

        self::assertInstanceOf(ReconfigureSequence::class, $value);
        self::assertSame('ALTER SEQUENCE "public"."ticket" START WITH 5 RESTART OWNED BY "public"."receiver"."id"', $value->toString());
    }

    public function testLowerRefusesRepeatedSequenceRoles(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.ticket']);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence option may be specified only once.');

        $semantics->analyze('ALTER SEQUENCE ticket RESTART WITH 1 RESTART', $context);
    }
}
