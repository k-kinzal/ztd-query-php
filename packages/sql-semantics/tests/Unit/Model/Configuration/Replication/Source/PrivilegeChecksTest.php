<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Replication\Source\PrivilegeChecks;
use SqlSemantics\Model\Configuration\Replication\Source\SourceOption;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationSourceStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrivilegeChecks::class)]
#[Medium]
final class PrivilegeChecksTest extends TestCase
{
    public function testOptionIsPrivilegeChecksUser(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = applier');
        self::assertInstanceOf(ChangeReplicationSourceStatement::class, $statement);
        self::assertEquals(new PrivilegeChecks(new AccountName('applier')), $statement->settings[0]);
        self::assertSame(SourceOption::PrivilegeChecksUser, $statement->settings[0]->option());
        self::assertNull((new PrivilegeChecks(null))->account);
    }
}
