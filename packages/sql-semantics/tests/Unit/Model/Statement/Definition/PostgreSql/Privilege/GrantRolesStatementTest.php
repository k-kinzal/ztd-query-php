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
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantAttribute;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\RoleGrantOption;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantRolesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GrantRolesStatement::class)]
#[Medium]
final class GrantRolesStatementTest extends TestCase
{
    public function testReadsAMembershipGrantFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertEquals([new NamedRole('staff'), new NamedRole('select')], $statement->roles);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertEquals([new RoleGrantOption(RoleGrantAttribute::Admin, true), new RoleGrantOption(RoleGrantAttribute::Set, false)], $statement->options);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertSame(StatementKind::Grant, $statement->kind);
    }

    public function testToStringWritesEveryOptionAsAnExplicitBoolean(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertSame('GRANT "staff", "select" TO "alice" WITH ADMIN TRUE, SET FALSE GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob', 'GRANT "staff", "select" TO "alice" WITH ADMIN TRUE, SET FALSE GRANTED BY "bob"'])]
    #[TestWith(['GRANT staff TO alice', 'GRANT "staff" TO "alice"'])]
    #[TestWith(['GRANT staff TO alice WITH INHERIT TRUE', 'GRANT "staff" TO "alice" WITH INHERIT TRUE'])]
    #[TestWith(['GRANT staff TO alice, CURRENT_ROLE GRANTED BY CURRENT_USER', 'GRANT "staff" TO "alice", CURRENT_ROLE GRANTED BY CURRENT_USER'])]
    public function testRebindingTheOutputReachesAFixedPoint(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $again = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(GrantRolesStatement::class, $again);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($again));
    }

    public function testWithRolesReplacesAndQuotesTheGrantedRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $roles = [new NamedRole('x"y')];
        $changed = $statement->withRoles($roles);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('staff'), new NamedRole('select')], $statement->roles);
        self::assertEquals($roles, $changed->roles);
        self::assertSame('GRANT "x""y" TO "alice" WITH ADMIN TRUE, SET FALSE GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithGranteesKeepsQuotedNamesSeparateFromSessionRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $grantees = [new NamedRole('CURRENT_USER'), SessionRole::CurrentRole];
        $changed = $statement->withGrantees($grantees);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new NamedRole('alice')], $statement->grantees);
        self::assertEquals($grantees, $changed->grantees);
        self::assertSame('GRANT "staff", "select" TO "CURRENT_USER", CURRENT_ROLE WITH ADMIN TRUE, SET FALSE GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOptionsReplacesOrClearsTheOrderedOptionList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $options = [new RoleGrantOption(RoleGrantAttribute::Inherit, true)];
        $changed = $statement->withOptions($options);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new RoleGrantOption(RoleGrantAttribute::Admin, true), new RoleGrantOption(RoleGrantAttribute::Set, false)], $statement->options);
        self::assertEquals($options, $changed->options);
        self::assertSame('GRANT "staff", "select" TO "alice" WITH INHERIT TRUE GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        $cleared = $statement->withOptions([]);
        self::assertSame([], $cleared->options);
        self::assertSame('GRANT "staff", "select" TO "alice" GRANTED BY "bob"', (new \SqlSemantics\SimpleSerializer())->serialize($cleared));
    }

    public function testWithGrantorRemovesOrReplacesTheGrantor(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $removed = $statement->withGrantor(null);
        self::assertNotSame($statement, $removed);
        self::assertEquals(new NamedRole('bob'), $statement->grantor);
        self::assertNull($removed->grantor);
        self::assertSame('GRANT "staff", "select" TO "alice" WITH ADMIN TRUE, SET FALSE', (new \SqlSemantics\SimpleSerializer())->serialize($removed));
        $session = $statement->withGrantor(SessionRole::SessionUser);
        self::assertSame(SessionRole::SessionUser, $session->grantor);
        self::assertSame('GRANT "staff", "select" TO "alice" WITH ADMIN TRUE, SET FALSE GRANTED BY SESSION_USER', (new \SqlSemantics\SimpleSerializer())->serialize($session));
    }

    public function testWithOriginRetainsEveryOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->roles, $copy->roles);
        self::assertSame($statement->grantees, $copy->grantees);
        self::assertSame($statement->options, $copy->options);
        self::assertSame($statement->grantor, $copy->grantor);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT staff, select TO alice WITH ADMIN OPTION, SET FALSE GRANTED BY bob');
        self::assertInstanceOf(GrantRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsAForeignOriginOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        new GrantRolesStatement($origin, [new NamedRole('staff')], [new NamedRole('alice')]);
    }
}
