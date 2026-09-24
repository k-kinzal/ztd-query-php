<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourceOption::class)]
#[Medium]
final class SourceOptionTest extends TestCase
{
    public function testSpelledReadsBothVocabularies(): void
    {
        self::assertSame(SourceOption::Host, SourceOption::spelled('master_host'));
        self::assertSame(SourceOption::GetPublicKey, SourceOption::spelled('GET_MASTER_PUBLIC_KEY'));
        self::assertSame(SourceOption::IgnoreServerIds, SourceOption::spelled('IGNORE_SERVER_IDS'));
        self::assertNull(SourceOption::spelled('MASTER_UNKNOWN'));
    }

    public function testLegacyReturnsTheMasterSpelling(): void
    {
        self::assertSame('MASTER_LOG_POS', SourceOption::LogPosition->legacy());
        self::assertSame('RELAY_LOG_FILE', SourceOption::RelayLogFile->legacy());
    }

    public function testSinceReturnsTheFirstAcceptingRelease(): void
    {
        self::assertSame(0, SourceOption::Port->since());
        self::assertSame(50700, SourceOption::TlsVersion->since());
        self::assertSame(80000, SourceOption::GtidOnly->since());
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("CHANGE MASTER TO MASTER_HOST = 'h'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ChangeReplicationSourceStatement($statement->origin, [new \SqlSemantics\Model\Configuration\Replication\Source\SourceFlag(SourceOption::GtidOnly, true)]);
    }
}
