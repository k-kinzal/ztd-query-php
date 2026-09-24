<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Configuration\Replication\Source\SourceSetting;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SourceSetting::class)]
#[Medium]
final class SourceSettingTest extends TestCase
{
    public function testOptionNamesEveryKindOfSetting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION SOURCE TO SOURCE_HOST = 'h', SOURCE_PORT = 1, SOURCE_SSL = 1, IGNORE_SERVER_IDS = (), PRIVILEGE_CHECKS_USER = NULL, REQUIRE_TABLE_PRIMARY_KEY_CHECK = ON, ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = OFF");
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertSame(
            [SourceOption::Host, SourceOption::Port, SourceOption::Ssl, SourceOption::IgnoreServerIds, SourceOption::PrivilegeChecksUser, SourceOption::RequireTablePrimaryKeyCheck, SourceOption::AssignGtidsToAnonymousTransactions],
            array_map(static fn (SourceSetting $setting): SourceOption => $setting->option(), $statement->settings),
        );
    }
}
