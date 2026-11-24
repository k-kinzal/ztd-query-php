<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(MoveSequence::class)]
final class MoveSequenceTest extends TestCase
{
    public function testToStringEmitsAnIndependentDestinationIdentifier(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('public'));
        $move = new MoveSequence($source, new Identifier('a."棚"'));

        self::assertSame('ALTER SEQUENCE "public"."ticket" SET SCHEMA "a.""棚"""', $move->toString());
    }

    public function testProfileIdKeepsTheSelectedRelease(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('public'));
        $move = new MoveSequence($source, new Identifier('public'), release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $move->profileId());
    }

    public function testRejectsADestinationOnTheAbsentRole(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), null, SequencePresence::MissingSequence);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('An absent sequence move has no destination namespace.');

        new MoveSequence($source, new Identifier('public'), true);
    }

    public function testRejectsAMoveOutOfTheSessionNamespace(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('pg_temp'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence move requires a persistent source namespace.');

        new MoveSequence($source, new Identifier('public'));
    }
}
