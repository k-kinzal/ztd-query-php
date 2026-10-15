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

    public function testAcceptsRequiresATextualUuidPrefixForAnonymousGtids(): void
    {
        $kind = SourceOptionKind::AssignGtidsToAnonymousTransactions;
        self::assertFalse($kind->accepts(new Text('')));
        self::assertFalse($kind->accepts(new Text('3e11fa4771ca11e19e33c80aa9429562')));
        self::assertFalse($kind->accepts(new Text('3e11fa47-71ca-11e1-9e33-c80aa942956z')));
        self::assertFalse($kind->accepts(new Text(' 3e11fa47-71ca-11e1-9e33-c80aa9429562')));
        self::assertTrue($kind->accepts(new Text('3e11fa47-71ca-11e1-9e33-c80aa9429562')));
        self::assertTrue($kind->accepts(new Text('3E11FA47-71CA-11E1-9E33-C80AA9429562 trailing')));
    }

    public function testPositionNamesTheLogPositionOptions(): void
    {
        self::assertTrue(SourceOptionKind::RelayLogFile->position());
        self::assertFalse(SourceOptionKind::Port->position());
    }

    public function testAcceptsDistinguishesSourceAndRelayLogNumerals(): void
    {
        self::assertFalse(SourceOptionKind::LogPosition->accepts(new Numeral('b', true)));
        self::assertTrue(SourceOptionKind::RelayLogPosition->accepts(new Numeral('b', true)));
        self::assertTrue(SourceOptionKind::LogPosition->accepts(new Numeral('1.5')));
        self::assertTrue(SourceOptionKind::LogPosition->accepts(new Numeral('2147483648')));
    }
    public function testAcceptsRestrictsSourceSwitchesToTheirBooleanNumericDomain(): void
    {
        self::assertTrue(SourceOptionKind::GtidOnly->accepts(new Numeral('01')));
        self::assertTrue(SourceOptionKind::RequireRowFormat->accepts(new Numeral('1.9')));
        self::assertTrue(SourceOptionKind::ConnectionAutoFailover->accepts(new Numeral('1', true)));
        self::assertFalse(SourceOptionKind::GtidOnly->accepts(new Numeral('1.0')));
        self::assertFalse(SourceOptionKind::RequireRowFormat->accepts(new Numeral('2')));
        self::assertFalse(SourceOptionKind::ConnectionAutoFailover->accepts(new Numeral('2')));
    }

}
