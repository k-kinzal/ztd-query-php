<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\PublicRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\DefaultPrivilegeTarget;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\PrivilegeInvariant;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrivilegeInvariant::class)]
#[Medium]
final class PrivilegeInvariantTest extends TestCase
{
    public function testDialectAcceptsAPostgreSqlOrigin(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        PrivilegeInvariant::dialect($origin);
        self::assertSame(Dialect::PostgreSql, $origin->dialect);
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDialectRejectsAnotherDatabaseLanguage(Dialect $dialect): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::dialect($origin);
    }

    public function testPrivilegesAcceptsIndividualRequestsAndALoneAllPrivileges(): void
    {
        PrivilegeInvariant::privileges([new ObjectPrivilege(Privilege::Select), new ColumnPrivilege(Privilege::Update, ['a'])]);
        PrivilegeInvariant::privileges([new ColumnPrivilege(Privilege::All, ['a'])]);
        self::assertTrue(Privilege::All->columnar());
    }

    public function testPrivilegesRejectsAllPrivilegesBesideAnotherRequest(): void
    {
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::privileges([new ObjectPrivilege(Privilege::Select), new ObjectPrivilege(Privilege::All)]);
    }

    public function testPrivilegesRejectsAnEmptyRequestList(): void
    {
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::privileges([]);
    }

    public function testGranteesAcceptsPublicOnlyWhenAdmitted(): void
    {
        PrivilegeInvariant::grantees([new NamedRole('a'), SessionRole::CurrentUser, PublicRole::Public], true);
        PrivilegeInvariant::grantees([new NamedRole('a'), SessionRole::CurrentUser], false);
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::grantees([PublicRole::Public], false);
    }

    public function testGranteesRejectsAnEmptyList(): void
    {
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::grantees([], true);
    }

    public function testMismatchAcceptsColumnsOnTablesAndOnAllTablesInSchema(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(TableTargets::class, $statement->target);
        $columns = [new ColumnPrivilege(Privilege::Select, ['id'])];
        self::assertNull(PrivilegeInvariant::mismatch($statement->target, $columns));
        self::assertNull(PrivilegeInvariant::mismatch(new SchemaScopedTargets(SchemaScopedClass::Tables, ['s']), $columns));
    }

    public function testMismatchDiagnosesColumnsOnANonTableClass(): void
    {
        $columns = [new ColumnPrivilege(Privilege::Select, ['id'])];
        self::assertSame(InputViolation::ColumnPrivilege, PrivilegeInvariant::mismatch(new ServerObjectTargets(ServerObjectClass::Database, ['d']), $columns));
        self::assertSame(InputViolation::ColumnPrivilege, PrivilegeInvariant::mismatch(new SchemaScopedTargets(SchemaScopedClass::Sequences, ['s']), $columns));
        self::assertSame(InputViolation::ColumnPrivilege, PrivilegeInvariant::mismatch(DefaultPrivilegeTarget::Tables, $columns));
    }

    public function testMismatchDiagnosesAPrivilegeOutsideTheClassDomain(): void
    {
        self::assertSame(InputViolation::PrivilegeTarget, PrivilegeInvariant::mismatch(new ServerObjectTargets(ServerObjectClass::Database, ['d']), [new ObjectPrivilege(Privilege::Insert)]));
        self::assertSame(InputViolation::PrivilegeTarget, PrivilegeInvariant::mismatch(new LargeObjectTargets([1]), [new ObjectPrivilege(Privilege::Select), new ObjectPrivilege(Privilege::Delete)]));
        self::assertNull(PrivilegeInvariant::mismatch(new ServerObjectTargets(ServerObjectClass::Database, ['d']), [new ObjectPrivilege(Privilege::All)]));
        self::assertNull(PrivilegeInvariant::mismatch(new LargeObjectTargets([1]), [new ObjectPrivilege(Privilege::Select), new ObjectPrivilege(Privilege::Update)]));
    }

    public function testTargetAcceptsAValidRequest(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        PrivilegeInvariant::target($origin, new ParameterTargets([new QualifiedName(['work_mem'])]), [new ObjectPrivilege(Privilege::Set)]);
        PrivilegeInvariant::target($origin, DefaultPrivilegeTarget::Schemas, [new ObjectPrivilege(Privilege::Create)]);
        self::assertSame(Dialect::PostgreSql, $origin->dialect);
    }

    public function testTargetRejectsAPrivilegeOutsideTheClassDomain(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage(InputViolation::PrivilegeTarget->message());
        PrivilegeInvariant::target($origin, new ParameterTargets([new QualifiedName(['work_mem'])]), [new ObjectPrivilege(Privilege::Select)]);
    }

    public function testTargetRejectsColumnsOnANonTableClass(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage(InputViolation::ColumnPrivilege->message());
        PrivilegeInvariant::target($origin, new SchemaObjectTargets(SchemaObjectClass::Sequence, [new QualifiedName(['s'])]), [new ColumnPrivilege(Privilege::Select, ['a'])]);
    }

    public function testTargetRejectsAnotherDatabaseLanguage(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::target($origin, new LargeObjectTargets([1]), [new ObjectPrivilege(Privilege::Select)]);
    }

    public function testDomainListsThePrivilegesOfEachObjectClass(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame(PrivilegeInvariant::relation(true), PrivilegeInvariant::domain($statement->target));
        self::assertSame([Privilege::Execute], PrivilegeInvariant::domain(new RoutineTargets(RoutineClass::Routine, [new RoutineByName(new QualifiedName(['f']))])));
        self::assertSame([Privilege::Select, Privilege::Update], PrivilegeInvariant::domain(new LargeObjectTargets([1])));
        self::assertSame([Privilege::Set, Privilege::AlterSystem], PrivilegeInvariant::domain(new ParameterTargets([new QualifiedName(['work_mem'])])));
        self::assertSame(PrivilegeInvariant::sequence(), PrivilegeInvariant::domain(new SchemaObjectTargets(SchemaObjectClass::Sequence, [new QualifiedName(['s'])])));
        self::assertSame([Privilege::Usage], PrivilegeInvariant::domain(new SchemaObjectTargets(SchemaObjectClass::Domain, [new QualifiedName(['d'])])));
        self::assertSame([Privilege::Usage], PrivilegeInvariant::domain(new SchemaObjectTargets(SchemaObjectClass::Type, [new QualifiedName(['ty'])])));
        self::assertSame(PrivilegeInvariant::server(ServerObjectClass::Schema), PrivilegeInvariant::domain(new ServerObjectTargets(ServerObjectClass::Schema, ['s'])));
        self::assertSame(PrivilegeInvariant::scoped(SchemaScopedClass::Functions), PrivilegeInvariant::domain(new SchemaScopedTargets(SchemaScopedClass::Functions, ['s'])));
        self::assertSame(PrivilegeInvariant::defaulted(DefaultPrivilegeTarget::Types), PrivilegeInvariant::domain(DefaultPrivilegeTarget::Types));
    }

    public function testRelationAdmitsUsageOnlyWhenSequencesMayBeNamed(): void
    {
        $relation = [Privilege::Select, Privilege::Insert, Privilege::Update, Privilege::Delete, Privilege::Truncate, Privilege::References, Privilege::Trigger, Privilege::Maintain];
        self::assertSame($relation, PrivilegeInvariant::relation(false));
        self::assertSame([...$relation, Privilege::Usage], PrivilegeInvariant::relation(true));
    }

    public function testSequenceListsUsageSelectAndUpdate(): void
    {
        self::assertSame([Privilege::Usage, Privilege::Select, Privilege::Update], PrivilegeInvariant::sequence());
    }

    public function testServerListsThePrivilegesOfEachUnqualifiedClass(): void
    {
        self::assertSame([Privilege::Create, Privilege::Connect, Privilege::Temporary], PrivilegeInvariant::server(ServerObjectClass::Database));
        self::assertSame([Privilege::Create, Privilege::Usage], PrivilegeInvariant::server(ServerObjectClass::Schema));
        self::assertSame([Privilege::Create], PrivilegeInvariant::server(ServerObjectClass::Tablespace));
        self::assertSame([Privilege::Usage], PrivilegeInvariant::server(ServerObjectClass::ForeignDataWrapper));
        self::assertSame([Privilege::Usage], PrivilegeInvariant::server(ServerObjectClass::ForeignServer));
        self::assertSame([Privilege::Usage], PrivilegeInvariant::server(ServerObjectClass::Language));
    }

    public function testScopedFollowsTheMemberClassDomain(): void
    {
        self::assertSame(PrivilegeInvariant::relation(true), PrivilegeInvariant::scoped(SchemaScopedClass::Tables));
        self::assertSame(PrivilegeInvariant::sequence(), PrivilegeInvariant::scoped(SchemaScopedClass::Sequences));
        self::assertSame([Privilege::Execute], PrivilegeInvariant::scoped(SchemaScopedClass::Functions));
        self::assertSame([Privilege::Execute], PrivilegeInvariant::scoped(SchemaScopedClass::Procedures));
        self::assertSame([Privilege::Execute], PrivilegeInvariant::scoped(SchemaScopedClass::Routines));
    }

    public function testDefaultedExcludesUsageFromFutureTables(): void
    {
        self::assertSame(PrivilegeInvariant::relation(false), PrivilegeInvariant::defaulted(DefaultPrivilegeTarget::Tables));
        self::assertSame(PrivilegeInvariant::sequence(), PrivilegeInvariant::defaulted(DefaultPrivilegeTarget::Sequences));
        self::assertSame([Privilege::Execute], PrivilegeInvariant::defaulted(DefaultPrivilegeTarget::Functions));
        self::assertSame([Privilege::Usage], PrivilegeInvariant::defaulted(DefaultPrivilegeTarget::Types));
        self::assertSame([Privilege::Create, Privilege::Usage], PrivilegeInvariant::defaulted(DefaultPrivilegeTarget::Schemas));
    }

    public function testDefaultsAcceptsRolesAndSchemasForNonSchemaClasses(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        PrivilegeInvariant::defaults($origin, DefaultPrivilegeTarget::Tables, [new ObjectPrivilege(Privilege::Select)], [new NamedRole('owner'), SessionRole::CurrentRole], ['app']);
        PrivilegeInvariant::defaults($origin, DefaultPrivilegeTarget::Schemas, [new ObjectPrivilege(Privilege::Usage)], [], []);
        self::assertSame(Dialect::PostgreSql, $origin->dialect);
    }

    public function testDefaultsRejectsASchemaSelectionForSchemas(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::defaults($origin, DefaultPrivilegeTarget::Schemas, [new ObjectPrivilege(Privilege::Usage)], [], ['app']);
    }

    public function testDefaultsRejectsAnEmptySchemaName(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        PrivilegeInvariant::defaults($origin, DefaultPrivilegeTarget::Tables, [new ObjectPrivilege(Privilege::Select)], [], ['']);
    }

    public function testDefaultsRejectsUsageOnFutureTables(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage(InputViolation::PrivilegeTarget->message());
        PrivilegeInvariant::defaults($origin, DefaultPrivilegeTarget::Tables, [new ObjectPrivilege(Privilege::Usage)], [], []);
    }
}
