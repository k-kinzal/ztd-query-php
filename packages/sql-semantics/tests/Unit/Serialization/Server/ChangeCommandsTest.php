<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\WildTableFilter;
use SqlSemantics\Model\Configuration\Replication\Source\AnonymousGtids;
use SqlSemantics\Model\Configuration\Replication\Source\PrivilegeChecks;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Server\ChangeCommands;

#[CoversClass(ChangeCommands::class)]
#[Medium]
final class ChangeCommandsTest extends TestCase
{
    public function testSourceWritesTheReleaseVocabulary(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("CHANGE MASTER TO MASTER_SSL = 5, IGNORE_SERVER_IDS = (1, 2), MASTER_HEARTBEAT_PERIOD = 0.5 FOR CHANNEL 'c'");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame('change-replication-source', ChangeCommands::source($statement)->role);
        self::assertSame("CHANGE MASTER TO MASTER_SSL = 1, IGNORE_SERVER_IDS = (1, 2), MASTER_HEARTBEAT_PERIOD = 0.5 FOR CHANNEL 'c'", $statement->toString());
    }

    public function testSettingWritesKeywordValues(): void
    {
        self::assertSame('keyword', ChangeCommands::setting(AnonymousGtids::Local)->role);
        self::assertSame('keyword', ChangeCommands::setting(new PrivilegeChecks(null))->role);
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', REQUIRE_TABLE_PRIMARY_KEY_CHECK = OFF");
        self::assertSame("CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', REQUIRE_TABLE_PRIMARY_KEY_CHECK = OFF", $statement->toString());
    }

    public function testFilterWritesEveryRule(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = ('a.%'), REPLICATE_IGNORE_DB = (b) FOR CHANNEL 'c'");
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertSame('change-replication-filter', ChangeCommands::filter($statement)->role);
        self::assertSame("CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = ('a.%'), REPLICATE_IGNORE_DB = (`b`) FOR CHANNEL 'c'", $statement->toString());
    }

    public function testValuesWritesOneTreePerListedValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_WILD_IGNORE_TABLE = ('a.%', 'b.%')");
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertInstanceOf(WildTableFilter::class, $statement->filters[0]);
        self::assertCount(2, ChangeCommands::values($statement->filters[0]));
    }
}
