<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\Source\SourceFlag;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Configuration\Replication\Source\SourceSettings;
use SqlSemantics\Model\Configuration\Replication\Source\SourceText;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourceSettings::class)]
#[Medium]
final class SourceSettingsTest extends TestCase
{
    public function testCheckRejectsARepeatedOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO SOURCE_SSL = 1');
        SourceSettings::check($statement->origin, [new SourceFlag(SourceOption::Ssl, true), new SourceFlag(SourceOption::GtidOnly, false)]);
        $this->expectException(InvalidStructure::class);
        SourceSettings::check($statement->origin, [new SourceFlag(SourceOption::Ssl, true), new SourceFlag(SourceOption::Ssl, false)]);
    }

    public function testCheckLimitsThePasswordLengthFromMySql57(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ReplicationOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("CHANGE MASTER TO MASTER_PASSWORD = '" . str_repeat('p', 33) . "'");
    }

    public function testPasswordAppliesFromMySql57(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("CHANGE MASTER TO MASTER_PASSWORD = '" . str_repeat('p', 33) . "'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $legacy);
        SourceSettings::password(50651, $legacy->settings);
        $this->expectException(InvalidStructure::class);
        SourceSettings::password(50700, $legacy->settings);
    }

    public function testCoordinatesRejectsLogCoordinatesWithAutoPosition(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SourceCoordinates->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION = 1, RELAY_LOG_FILE = 'r'");
    }

    public function testCoordinatesRejectsSourceAndRelayCoordinatesTogether(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_AUTO_POSITION = 0, SOURCE_LOG_FILE = 'b', SOURCE_LOG_POS = 4");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertCount(3, $statement->settings);
        $this->expectException(InvalidStructure::class);
        SourceSettings::coordinates(['SOURCE_LOG_POS' => new SourceFlag(SourceOption::Ssl, true), 'RELAY_LOG_POS' => new SourceFlag(SourceOption::Ssl, true)]);
    }

    public function testCheckRejectsAnOptionBeforeItsRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('GTID_ONLY appears once, in a release that accepts it.');
        SourceSettings::check($origin, [new SourceFlag(SourceOption::GtidOnly, true)]);
    }

    public function testCheckLimitsThePasswordOfAKnownRelease(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1')->origin;
        $password = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'" . str_repeat('p', 33) . "'", 0));
        self::assertInstanceOf(Literal::class, $password);
        $this->expectException(InvalidStructure::class);
        SourceSettings::check($origin, [new SourceText(SourceOption::Password, $password)]);
    }

    public function testCheckRejectsCoordinatesWithAutoPosition(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $file = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'r'", 0));
        self::assertInstanceOf(Literal::class, $file);
        $this->expectException(InvalidStructure::class);
        SourceSettings::check($origin, [new SourceFlag(SourceOption::AutoPosition, true), new SourceText(SourceOption::RelayLogFile, $file)]);
    }

    public function testCheckAcceptsAutoPositionWithOtherOptions(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectNotToPerformAssertions();
        SourceSettings::check($origin, [new SourceFlag(SourceOption::AutoPosition, true), new SourceFlag(SourceOption::Ssl, true)]);
    }

    public function testPasswordAcceptsThirtyTwoCharacters(): void
    {
        $password = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'" . str_repeat('p', 32) . "'", 0));
        self::assertInstanceOf(Literal::class, $password);
        SourceSettings::password(50700, [new SourceText(SourceOption::Password, $password)]);
        self::assertSame(34, strlen($password->text));
    }

    public function testCoordinatesRejectsASourceFileWithARelayFile(): void
    {
        $file = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'r'", 0));
        self::assertInstanceOf(Literal::class, $file);
        $this->expectException(InvalidStructure::class);
        SourceSettings::coordinates(['SOURCE_LOG_FILE' => new SourceText(SourceOption::LogFile, $file), 'RELAY_LOG_FILE' => new SourceText(SourceOption::RelayLogFile, $file)]);
    }

    public function testCoordinatesAcceptsRelayCoordinatesAlone(): void
    {
        $file = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'r'", 0));
        self::assertInstanceOf(Literal::class, $file);
        $relay = new SourceText(SourceOption::RelayLogFile, $file);
        SourceSettings::coordinates(['RELAY_LOG_FILE' => $relay]);
        self::assertSame(SourceOption::RelayLogFile, $relay->option());
    }
}
