<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Privilege;

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
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GrantPrivilegesStatement::class)]
#[Medium]
final class GrantPrivilegesStatementTest extends TestCase
{
    public function testReadsAColumnGrantFromBoundSql(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new ColumnPrivilege(Privilege::Select, ['id']), new ObjectPrivilege(Privilege::Insert)], $statement->privileges);
        self::assertInstanceOf(TableTargets::class, $statement->target);
        self::assertSame(['public', 't'], $statement->target->tables[0]->name->parts);
        self::assertEquals([PublicRole::Public, new NamedRole('alice')], $statement->grantees);
        self::assertTrue($statement->grantOption);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertSame(StatementKind::Grant, $statement->kind);
    }

    public function testToStringQualifiesTheTableAndQuotesColumnsAndRoles(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertSame('GRANT SELECT ("id"), INSERT ON TABLE "public"."t" TO PUBLIC, "alice" WITH GRANT OPTION GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob', 'GRANT SELECT ("id"), INSERT ON TABLE "public"."t" TO PUBLIC, "alice" WITH GRANT OPTION GRANTED BY "bob"'])]
    #[TestWith(['GRANT USAGE ON SEQUENCE s TO a', 'GRANT USAGE ON SEQUENCE "s" TO "a"'])]
    #[TestWith(['GRANT EXECUTE ON FUNCTION f, g(), app.h(IN id integer) TO a', 'GRANT EXECUTE ON FUNCTION "f", "g"(), "app"."h"(IN "id" integer) TO "a"'])]
    #[TestWith(['GRANT SELECT, UPDATE ON LARGE OBJECT 12, 13 TO a', 'GRANT SELECT, UPDATE ON LARGE OBJECT 12, 13 TO "a"'])]
    #[TestWith(['GRANT SET ON PARAMETER work_mem TO a', 'GRANT SET ON PARAMETER "work_mem" TO "a"'])]
    #[TestWith(['GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA s TO a', 'GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA "s" TO "a"'])]
    #[TestWith(['GRANT CONNECT ON DATABASE d TO CURRENT_USER', 'GRANT CONNECT ON DATABASE "d" TO CURRENT_USER'])]
    public function testRebindingTheOutputReachesAFixedPointForEveryTargetClass(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(GrantPrivilegesStatement::class, $again);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithPrivilegesReplacesTheCompleteRequestWithoutMutatingTheOriginal(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $privileges = [new ObjectPrivilege(Privilege::All)];
        $changed = $statement->withPrivileges($privileges);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new ColumnPrivilege(Privilege::Select, ['id']), new ObjectPrivilege(Privilege::Insert)], $statement->privileges);
        self::assertEquals($privileges, $changed->privileges);
        self::assertSame('GRANT ALL PRIVILEGES ON TABLE "public"."t" TO PUBLIC, "alice" WITH GRANT OPTION GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithPrivilegesRejectsAllPrivilegesCombinedWithAnother(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withPrivileges([new ObjectPrivilege(Privilege::All), new ObjectPrivilege(Privilege::Select)]);
    }

    public function testWithTargetReplacesTheObjectSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT USAGE ON SEQUENCE s TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf(SchemaObjectTargets::class, $statement->target);
        self::assertSame(SchemaObjectClass::Sequence, $statement->target->class);
        $target = new ServerObjectTargets(ServerObjectClass::Language, ['plpgsql']);
        $changed = $statement->withTarget($target);
        self::assertNotSame($statement, $changed);
        self::assertEquals($target, $changed->target);
        self::assertEquals($statement->privileges, $changed->privileges);
        self::assertSame('GRANT USAGE ON LANGUAGE "plpgsql" TO "a"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithTargetRejectsColumnsOnANonTableClass(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTarget(new ServerObjectTargets(ServerObjectClass::Database, ['app']));
    }

    public function testWithTargetRejectsAPrivilegeOutsideTheClassDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT SELECT, UPDATE ON LARGE OBJECT 12, 13 TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withTarget(new ParameterTargets([new QualifiedName(['work_mem'])]));
    }

    public function testWithGranteesReplacesTheRecipients(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $grantees = [SessionRole::CurrentRole, new NamedRole('x"y')];
        $changed = $statement->withGrantees($grantees);
        self::assertNotSame($statement, $changed);
        self::assertEquals([PublicRole::Public, new NamedRole('alice')], $statement->grantees);
        self::assertEquals($grantees, $changed->grantees);
        self::assertSame('GRANT SELECT ("id"), INSERT ON TABLE "public"."t" TO CURRENT_ROLE, "x""y" WITH GRANT OPTION GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGrantOptionRemovesTheOnwardGrant(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $changed = $statement->withGrantOption(false);
        self::assertNotSame($statement, $changed);
        self::assertTrue($statement->grantOption);
        self::assertFalse($changed->grantOption);
        self::assertSame('GRANT SELECT ("id"), INSERT ON TABLE "public"."t" TO PUBLIC, "alice" GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGrantorReplacesOrRemovesTheGrantor(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $removed = $statement->withGrantor(null);
        self::assertNotSame($statement, $removed);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertNull($removed->grantor);
        self::assertSame('GRANT SELECT ("id"), INSERT ON TABLE "public"."t" TO PUBLIC, "alice" WITH GRANT OPTION', (new \SqlSemantics\SimpleSerializer())->serialize($removed));
        $session = $statement->withGrantor(SessionRole::CurrentUser);
        self::assertSame(SessionRole::CurrentUser, $session->grantor);
        self::assertSame('GRANT SELECT ("id"), INSERT ON TABLE "public"."t" TO PUBLIC, "alice" WITH GRANT OPTION GRANTED BY CURRENT_USER', (new \SqlSemantics\SimpleSerializer())->serialize($session));
    }

    public function testWithOriginRetainsEveryOperand(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)');
        $statement = (new Binder($schema))->bind('GRANT SELECT (id), INSERT ON TABLE t TO PUBLIC, alice WITH GRANT OPTION GRANTED BY bob');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->privileges, $copy->privileges);
        self::assertSame($statement->target, $copy->target);
        self::assertSame($statement->grantees, $copy->grantees);
        self::assertSame($statement->grantOption, $copy->grantOption);
        self::assertSame($statement->grantor, $copy->grantor);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT USAGE ON SEQUENCE s TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAPrivilegeOutsideTheClassDomainOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $target = new ServerObjectTargets(ServerObjectClass::Database, ['app']);
        $this->expectException(InvalidStructure::class);
        new GrantPrivilegesStatement($origin, [new ObjectPrivilege(Privilege::Insert)], $target, [PublicRole::Public]);
    }
}
