<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(SourceOptionKind::class)]
#[Small]
final class SourceOptionKindTest extends TestCase
{
    public function testKeywordSpellsBothVocabularies(): void
    {
        self::assertSame('GET_MASTER_PUBLIC_KEY', SourceOptionKind::GetPublicKey->keyword(Terminology::Legacy));
        self::assertSame('MASTER_LOG_POS', SourceOptionKind::LogPosition->keyword(Terminology::Legacy));
        self::assertSame('RELAY_LOG_POS', SourceOptionKind::RelayLogPosition->keyword(Terminology::Legacy));
        self::assertSame('SOURCE_HOST', SourceOptionKind::Host->keyword(Terminology::Current));
    }

    public function testRenamedExcludesTheOptionsWithOneName(): void
    {
        self::assertTrue(SourceOptionKind::Delay->renamed());
        self::assertFalse(SourceOptionKind::ConnectionAutoFailover->renamed());
        self::assertFalse(SourceOptionKind::GtidOnly->renamed());
    }

    public function testAcceptsChecksTheDomainOfTheOption(): void
    {
        self::assertTrue(SourceOptionKind::Host->accepts(new Text('h')));
        self::assertFalse(SourceOptionKind::Host->accepts(new Numeral('1')));
        self::assertTrue(SourceOptionKind::HeartbeatPeriod->accepts(new NumberLiteral('1.5')));
        self::assertTrue(SourceOptionKind::PrivilegeChecksUser->accepts(null));
        self::assertFalse(SourceOptionKind::Port->accepts(null));
        self::assertTrue(SourceOptionKind::AssignGtidsToAnonymousTransactions->accepts(AnonymousGtids::Local));
    }

    public function testPositionNamesTheLogPositionOptions(): void
    {
        self::assertTrue(SourceOptionKind::RelayLogFile->position());
        self::assertFalse(SourceOptionKind::Port->position());
    }
}
