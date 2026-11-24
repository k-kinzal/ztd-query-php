<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceSelector;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(SetSequenceLogging::class)]
final class SetSequenceLoggingTest extends TestCase
{
    public function testToStringRetainsOrderAndIsDetachedFromReferencedInputs(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('archive'));
        $first = SequenceLogging::Logged;
        $request = [&$first, SequenceLogging::Unlogged];
        $change = new SetSequenceLogging($source, $request);
        $first = SequenceLogging::Unlogged;

        self::assertSame([SequenceLogging::Logged, SequenceLogging::Unlogged], $change->modes);
        self::assertSame('ALTER SEQUENCE "archive"."ticket" SET LOGGED, SET UNLOGGED', $change->toString());
    }

    public function testProfileIdPreservesTheRelease(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('archive'));
        $change = new SetSequenceLogging($source, [SequenceLogging::Logged], release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $change->profileId());
    }

    public function testRejectsAPrefixWithNoAdmissibleInitialMode(): void
    {
        $source = new SequenceSelector(new Identifier('ticket'), new Identifier('archive'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A sequence logging prefix must request one consistent mode.');

        new SetSequenceLogging($source, [SequenceLogging::Logged, SequenceLogging::Unlogged, SequenceLogging::Logged]);
    }
}
