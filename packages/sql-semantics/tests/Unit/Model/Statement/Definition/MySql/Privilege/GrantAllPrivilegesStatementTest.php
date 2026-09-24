<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoleSelection;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantAllPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GrantAllPrivilegesStatement::class)]
#[Medium]
final class GrantAllPrivilegesStatementTest extends TestCase
{
    public function testWithTargetReplacesTheLevelWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT ALL ON *.* TO u');
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $statement);
        $changed = $statement->withTarget(new RoutineTarget(new QualifiedName(['app', 'p']), RoutineKind::Procedure));
        self::assertSame(PrivilegeScope::Global, $statement->target);
        self::assertSame("GRANT ALL PRIVILEGES ON PROCEDURE `app`.`p` TO 'u'", $changed->toString());
    }

    public function testWithGranteesAcceptsALegacyCredential(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('GRANT ALL ON *.* TO u');
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $statement);
        $changed = $statement->withGrantees([new AccountDefinition(new AccountName('v'), new PluginIdentification('auth_socket'))]);
        self::assertSame("GRANT ALL PRIVILEGES ON *.* TO 'v' IDENTIFIED WITH `auth_socket`", $changed->toString());
        self::assertEquals([new AccountName('u')], $statement->grantees);
    }

    public function testWithWithGrantOptionAddsTheOnwardGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT ALL ON * TO u');
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $statement);
        self::assertSame("GRANT ALL PRIVILEGES ON * TO 'u' WITH GRANT OPTION", $statement->withWithGrantOption(true)->toString());
        self::assertFalse($statement->withGrantOption);
    }

    public function testWithRequirementAddsALegacyRequirement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('GRANT ALL ON *.* TO u');
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $statement);
        self::assertSame("GRANT ALL PRIVILEGES ON *.* TO 'u' REQUIRE NONE", $statement->withRequirement(ConnectionSecurity::None)->toString());
        self::assertNull($statement->requirement);
    }

    public function testWithResourceLimitsRejectsTheMySql8Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT ALL ON *.* TO u');
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withResourceLimits([new ResourceLimit(ResourceLimitKind::QueriesPerHour, 1)]);
    }

    public function testWithGrantorAddsTheGrantorContext(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT ALL ON *.* TO u');
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $statement);
        $changed = $statement->withGrantor(new Grantor(new AccountName('g', 'h'), new RoleSelection([new AccountName('r')])));
        self::assertSame("GRANT ALL PRIVILEGES ON *.* TO 'u' AS 'g' @'h' WITH ROLE 'r'", $changed->toString());
        self::assertNull($statement->grantor);
    }

    public function testWithOriginRetainsTheCompleteGrant(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('GRANT ALL ON app.* TO u WITH GRANT OPTION AS g');
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }
}
