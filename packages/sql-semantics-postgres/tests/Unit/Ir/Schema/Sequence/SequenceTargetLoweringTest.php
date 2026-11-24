<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceTargetLowering;
use SqlSemantics\Platform\PostgreSql\Semantics;

#[CoversClass(SequenceTargetLowering::class)]
final class SequenceTargetLoweringTest extends TestCase
{
    public function testLowerDistinguishesMissingSequenceFromMissingNamespace(): void
    {
        $semantics = new Semantics();
        $drop = $semantics->analyze('DROP SEQUENCE IF EXISTS public.missing, unknown.missing', $semantics->context([]));

        self::assertInstanceOf(DropSequence::class, $drop);
        self::assertSame(SequencePresence::MissingSequence, $drop->targets[0]->presence);
        self::assertSame(SequencePresence::MissingNamespace, $drop->targets[1]->presence);
    }

    public function testPresentStopsAtAStockRelation(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE SEQUENCE public.pg_class']);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('The selected relation is not a sequence.');

        $semantics->analyze('DROP SEQUENCE IF EXISTS pg_class', $context);
    }
}
