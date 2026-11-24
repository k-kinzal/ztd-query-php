<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\NamedSequences;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(NamedSequences::class)]
final class NamedSequencesTest extends TestCase
{
    public function testNamedTargetMustBePresent(): void
    {
        $missing = new SequenceSelector(new Identifier('ticket'), new Identifier('public'), SequencePresence::MissingSequence);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Sequence privileges require an existing named sequence.');
        new NamedSequences([$missing]);
    }

    public function testCheckReleaseRejectsKnownNonSequenceOccupancy(): void
    {
        $target = new NamedSequences([new SequenceSelector(new Identifier('pg_class'), new Identifier('pg_catalog'))]);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A stock non-sequence relation cannot be a sequence target.');
        $target->checkRelease(GrammarRelease::PostgreSql172);
    }

    public function testToStringRetainsRepeatedTargets(): void
    {
        $sequence = new SequenceSelector(new Identifier('ticket'), new Identifier('public'));
        self::assertSame('SEQUENCE "public"."ticket", "public"."ticket"', (new NamedSequences([$sequence, $sequence]))->toString());
    }
}
