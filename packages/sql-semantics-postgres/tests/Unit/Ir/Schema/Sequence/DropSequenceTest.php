<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropTarget;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(DropSequence::class)]
final class DropSequenceTest extends TestCase
{
    public function testToStringPreservesOrderedRepeatsAndTheGuard(): void
    {
        $sequence = new SequenceDropTarget(new Identifier('ticket'), new Identifier('pg_temp'));
        $absent = new SequenceDropTarget(new Identifier('absent'), null, SequenceDropPresence::MissingSequence);
        $value = new DropSequence([$sequence, $absent, $sequence], true, true);

        self::assertSame('DROP SEQUENCE IF EXISTS "pg_temp"."ticket", "absent", "pg_temp"."ticket" CASCADE', $value->toString());
    }

    public function testProfileIdKeepsTheFixedRelease(): void
    {
        $sequence = new SequenceDropTarget(new Identifier('number'), new Identifier('public'));
        $value = new DropSequence([$sequence], release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }

    public function testRejectsContradictoryOccupancyWithinOneNamespace(): void
    {
        $namespace = new Identifier('archive');
        $sequence = new SequenceDropTarget(new Identifier('ticket'), $namespace);
        $missing = new SequenceDropTarget(new Identifier('other'), $namespace, SequenceDropPresence::MissingNamespace);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('Sequence removal cannot disagree about a named namespace.');

        new DropSequence([$sequence, $missing], true);
    }
}
