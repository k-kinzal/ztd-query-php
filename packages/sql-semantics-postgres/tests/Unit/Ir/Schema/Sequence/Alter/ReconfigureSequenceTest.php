<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceChanges;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceResetOperand;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;

#[CoversClass(ReconfigureSequence::class)]
final class ReconfigureSequenceTest extends TestCase
{
    public function testToStringWithAnAbsentSourceUsesOnlyTheRequiredSyntax(): void
    {
        $source = new SequenceSelector(new Identifier('gone'), null, SequencePresence::MissingSequence);
        $value = new ReconfigureSequence($source, null, true);

        self::assertSame('ALTER SEQUENCE IF EXISTS "gone" RESTART', $value->toString());
    }

    public function testProfileIdUsesTheExactPostgresProfile(): void
    {
        $source = new SequenceSelector(new Identifier('present'), new Identifier('public'));
        $value = new ReconfigureSequence($source, new SequenceChanges(restart: SequenceResetOperand::Native));

        self::assertSame('pg-17.2/utf8/standard-conforming-strings/native', $value->profileId());
    }

    public function testRejectsACrossNamespaceOwner(): void
    {
        $source = new SequenceSelector(new Identifier('present'), new Identifier('public'));
        $owner = new SequenceOwner(new Identifier('elsewhere'), new Identifier('records'), new Identifier('id'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('The sequence and its owning column must share a namespace.');

        new ReconfigureSequence($source, new SequenceChanges(owner: $owner));
    }
}
