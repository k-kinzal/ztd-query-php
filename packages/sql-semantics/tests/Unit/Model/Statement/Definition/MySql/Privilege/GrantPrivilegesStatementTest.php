<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GrantPrivilegesStatement::class)]
#[Medium]
final class GrantPrivilegesStatementTest extends TestCase
{
    public function testWithPrivilegesReplacesThePrivilegesWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $changed = $statement->withPrivileges([StaticPrivilege::Reload, new DynamicPrivilege('BACKUP_ADMIN')]);
        self::assertSame([StaticPrivilege::Select], $statement->privileges);
        self::assertSame("GRANT RELOAD, `BACKUP_ADMIN` ON *.* TO 'u'", $changed->toString());
    }

    public function testWithPrivilegesRejectsAColumnPrivilegeAtTheGlobalLevel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withPrivileges([new ColumnPrivilege(StaticPrivilege::Update, ['a'])]);
    }

    public function testWithTargetReplacesTheLevel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame("GRANT SELECT ON `app`.* TO 'u'", $statement->withTarget(new DatabaseScope('app'))->toString());
        self::assertSame(PrivilegeScope::Global, $statement->target);
    }

    public function testWithTargetRejectsALevelOutsideThePrivilegeDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT RELOAD ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTarget(PrivilegeScope::CurrentDatabase);
    }

    public function testWithGranteesReplacesTheRecipients(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame("GRANT SELECT ON *.* TO CURRENT_USER, 'v' @'h'", $statement->withGrantees([CurrentAccount::Authenticated, new AccountName('v', 'h')])->toString());
        self::assertEquals([new AccountName('u')], $statement->grantees);
    }

    public function testWithWithGrantOptionReplacesTheOnwardGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u WITH GRANT OPTION');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame("GRANT SELECT ON *.* TO 'u'", $statement->withWithGrantOption(false)->toString());
        self::assertTrue($statement->withGrantOption);
    }

    public function testWithRequirementAddsALegacyRequirement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame("GRANT SELECT ON *.* TO 'u' REQUIRE SSL", $statement->withRequirement(ConnectionSecurity::Ssl)->toString());
        self::assertNull($statement->requirement);
    }

    public function testWithRequirementRejectsTheMySql8Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRequirement(ConnectionSecurity::Ssl);
    }

    public function testWithResourceLimitsWritesThemAfterTheGrantOption(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('GRANT SELECT ON *.* TO u WITH GRANT OPTION');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $changed = $statement->withResourceLimits([new ResourceLimit(ResourceLimitKind::UserConnections, 2)]);
        self::assertSame("GRANT SELECT ON *.* TO 'u' WITH GRANT OPTION MAX_USER_CONNECTIONS 2", $changed->toString());
        self::assertSame([], $statement->resourceLimits);
    }

    public function testWithGrantorAddsTheGrantorContext(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame("GRANT SELECT ON *.* TO 'u' AS 'g' WITH ROLE ALL", $statement->withGrantor(new Grantor(new AccountName('g'), SessionRolePolicy::All))->toString());
        self::assertNull($statement->grantor);
    }

    public function testWithGrantorRejectsTheMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('GRANT SELECT ON *.* TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withGrantor(new Grantor(new AccountName('g')));
    }

    public function testWithOriginRetainsTheCompleteGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("GRANT SELECT ON *.* TO u IDENTIFIED BY 'x' REQUIRE SSL WITH GRANT OPTION MAX_QUERIES_PER_HOUR 3");
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsACredentialedGranteeOnMySql8(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("GRANT SELECT ON *.* TO u IDENTIFIED BY 'x'");
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
