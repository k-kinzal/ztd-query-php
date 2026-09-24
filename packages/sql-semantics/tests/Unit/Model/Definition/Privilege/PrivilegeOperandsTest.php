<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Privilege\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Definition\Privilege\DynamicPrivilege;
use SqlSemantics\Model\Definition\Privilege\Grantor;
use SqlSemantics\Model\Definition\Privilege\PrivilegeLevel;
use SqlSemantics\Model\Definition\Privilege\PrivilegeOperands;
use SqlSemantics\Model\Definition\Privilege\PrivilegeScope;
use SqlSemantics\Model\Definition\Privilege\RoutineTarget;
use SqlSemantics\Model\Definition\Privilege\StaticPrivilege;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrivilegeOperands::class)]
#[Medium]
final class PrivilegeOperandsTest extends TestCase
{
    public function testPrivilegesAcceptAColumnPrivilegeAtTheTableLevel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('GRANT SELECT ON t TO u');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        PrivilegeOperands::privileges($statement->origin, [new ColumnPrivilege(StaticPrivilege::Insert, ['a'])], $statement->target);
        self::assertSame(PrivilegeLevel::Table, PrivilegeOperands::level($statement->target));
    }

    public function testPrivilegesRejectADynamicPrivilegeBelowTheGlobalLevel(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::privileges($origin, [new DynamicPrivilege('BACKUP_ADMIN')], new DatabaseScope('app'));
    }

    public function testPrivilegesRejectADynamicPrivilegeOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::privileges($origin, [new DynamicPrivilege('BACKUP_ADMIN')], PrivilegeScope::Global);
    }

    public function testPrivilegesRejectAColumnPrivilegeAtTheDatabaseLevel(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::privileges($origin, [new ColumnPrivilege(StaticPrivilege::Select, ['a'])], PrivilegeScope::CurrentDatabase);
    }

    public function testPrivilegesRejectATablePrivilegeAtARoutineLevel(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::privileges($origin, [StaticPrivilege::Select], new RoutineTarget(new QualifiedName(['p']), RoutineKind::Procedure));
    }

    public function testPrivilegesRejectARolePrivilegeOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::privileges($origin, [StaticPrivilege::CreateRole], PrivilegeScope::Global);
    }


    public function testLevelClassifiesEachTarget(): void
    {
        self::assertSame(PrivilegeLevel::Global, PrivilegeOperands::level(PrivilegeScope::Global));
        self::assertSame(PrivilegeLevel::Database, PrivilegeOperands::level(PrivilegeScope::CurrentDatabase));
        self::assertSame(PrivilegeLevel::Database, PrivilegeOperands::level(new DatabaseScope('app')));
        self::assertSame(PrivilegeLevel::Routine, PrivilegeOperands::level(new RoutineTarget(new QualifiedName(['f']), RoutineKind::Function)));
    }

    public function testTargetRejectsAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::target($origin, PrivilegeScope::Global);
    }

    public function testRevocationRejectsAnExistencePolicyOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::revocation($origin, false, true);
    }

    public function testGranteesAcceptACredentialedLegacyGrantee(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        PrivilegeOperands::grantees($origin, [new AccountDefinition(new AccountName('u'), new PluginIdentification('p')), CurrentAccount::Authenticated], true);
        self::assertSame('mysql-5.7.44', $origin->context?->schema()->grammarVersion);
    }

    public function testGranteesRejectACredentialOnARevocation(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::grantees($origin, [new AccountDefinition(new AccountName('u'), new PluginIdentification('p'))], false);
    }

    public function testGranteesRejectACredentialedGranteeOnMySql8(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::grantees($origin, [new AccountDefinition(new AccountName('u'), RandomPassword::Generated)], true);
    }

    public function testClausesRejectARequirementOnMySql8(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::clauses($origin, ConnectionSecurity::Ssl, [], null);
    }

    public function testClausesRejectAGrantorOnMySql57(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeOperands::clauses($origin, null, [], new Grantor(new AccountName('g')));
    }
}
