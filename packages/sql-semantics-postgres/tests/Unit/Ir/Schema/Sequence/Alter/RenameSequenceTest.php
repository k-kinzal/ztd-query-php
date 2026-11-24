<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(RenameSequence::class)]
final class RenameSequenceTest extends TestCase
{
    public function testToStringKeepsTheGuardOnAnUnqualifiedAbsence(): void
    {
        $source = new SequenceSelector(new Identifier('old."棚"'), null, SequencePresence::MissingSequence);
        $rename = new RenameSequence($source, new Identifier('new.name'), true);

        self::assertSame('ALTER SEQUENCE IF EXISTS "old.""棚""" RENAME TO "new.name"', $rename->toString());
    }

    public function testProfileIdKeepsTheRelease(): void
    {
        $source = new SequenceSelector(new Identifier('a'), new Identifier('public'));
        $rename = new RenameSequence($source, new Identifier('b'), release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $rename->profileId());
    }

    public function testRejectsAnIdenticalDestinationForAPresentSequence(): void
    {
        $name = new Identifier('sequence');
        $source = new SequenceSelector($name, new Identifier('public'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A present sequence rename requires a distinct destination.');

        new RenameSequence($source, $name);
    }
}
