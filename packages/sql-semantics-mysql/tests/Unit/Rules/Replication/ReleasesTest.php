<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(Releases::class)]
#[Small]
final class ReleasesTest extends TestCase
{
    public function testSinceComparesTheReleaseOrder(): void
    {
        self::assertTrue((new Releases())->since(GrammarRelease::MySql901, GrammarRelease::MySql847));
        self::assertFalse((new Releases())->since(GrammarRelease::MySql830, GrammarRelease::MySql847));
    }

    public function testLegacyNamesThe5xGrammars(): void
    {
        self::assertTrue((new Releases())->legacy(GrammarRelease::MySql5744));
        self::assertFalse((new Releases())->legacy(GrammarRelease::MySql8044));
    }

    public function testSourceAnswersTheVocabularyOfChange(): void
    {
        self::assertSame(Terminology::Legacy, (new Releases())->source(GrammarRelease::MySql5651));
        self::assertSame(Terminology::Current, (new Releases())->source(GrammarRelease::MySql8044));
    }

    public function testBinaryLogsAnswersTheVocabularyOfResetMaster(): void
    {
        self::assertSame(Terminology::Legacy, (new Releases())->binaryLogs(GrammarRelease::MySql810));
        self::assertSame(Terminology::Current, (new Releases())->binaryLogs(GrammarRelease::MySql820));
    }

    public function testReplicaAcceptsEachSpellingWhereItExists(): void
    {
        self::assertTrue((new Releases())->replica(GrammarRelease::MySql830, Terminology::Legacy));
        self::assertFalse((new Releases())->replica(GrammarRelease::MySql847, Terminology::Legacy));
        self::assertFalse((new Releases())->replica(GrammarRelease::MySql5744, Terminology::Current));
    }

    public function testOptionAnswersTheReleasesOfAnOption(): void
    {
        self::assertFalse((new Releases())->option(GrammarRelease::MySql5651, SourceOptionKind::TlsVersion));
        self::assertTrue((new Releases())->option(GrammarRelease::MySql5744, SourceOptionKind::TlsVersion));
        self::assertFalse((new Releases())->option(GrammarRelease::MySql5744, SourceOptionKind::NetworkNamespace));
        self::assertTrue((new Releases())->option(GrammarRelease::MySql5651, SourceOptionKind::IgnoreServerIds));
    }
}
