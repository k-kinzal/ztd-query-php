<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Definition\Role\RoleKeyword;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateRoleStatement::class)]
#[Medium]
final class CreateRoleStatementTest extends TestCase
{
    public function testReadsTheNameTheKeywordAndTheOrderedOptionsFromBoundSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertEquals(new NamedRole('app'), $statement->name);
        self::assertSame(RoleKeyword::User, $statement->keyword);
        self::assertEquals([new RoleAttribute(RoleCapability::Login, true), new ConnectionLimit(10), new RoleMemberships([new NamedRole('a')])], $statement->options);
        self::assertSame(StatementKind::Create, $statement->kind);
    }

    public function testReadsThePasswordTheValidityTheMembersTheAdminsAndTheSystemId(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE ROLE r ENCRYPTED PASSWORD 'x' VALID UNTIL '2030-01-01' ROLE m ADMIN ad SYSID 5");
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertSame(RoleKeyword::Role, $statement->keyword);
        self::assertInstanceOf(RolePassword::class, $statement->options[0]);
        self::assertSame("'x'", $statement->options[0]->secret->text);
        self::assertInstanceOf(RoleValidity::class, $statement->options[1]);
        self::assertSame("'2030-01-01'", $statement->options[1]->until->text);
        self::assertEquals(new RoleMembers([new NamedRole('m')]), $statement->options[2]);
        self::assertEquals(new RoleAdmins([new NamedRole('ad')]), $statement->options[3]);
        self::assertEquals(new RoleSystemId(5), $statement->options[4]);
        self::assertSame("CREATE ROLE \"r\" PASSWORD 'x' VALID UNTIL '2030-01-01' ROLE \"m\" ADMIN \"ad\" SYSID 5", $statement->toString());
    }

    public function testToStringKeepsTheKeywordAndQuotesTheNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertSame('CREATE USER "app" LOGIN CONNECTION LIMIT 10 IN ROLE "a"', $statement->toString());
    }

    public function testRebindingTheOutputReachesAFixedPoint(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("CREATE GROUP g NOLOGIN PASSWORD 'x' IN GROUP a");
        $again = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateRoleStatement::class, $again);
        self::assertSame($statement->toString(), $again->toString());
    }

    public function testWithNameReplacesTheDefinedNameWithoutMutatingTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $changed = $statement->withName(new NamedRole('x"y'));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new NamedRole('app'), $statement->name);
        self::assertEquals(new NamedRole('x"y'), $changed->name);
        self::assertEquals($statement->options, $changed->options);
        self::assertSame($statement->keyword, $changed->keyword);
        self::assertSame('CREATE USER "x""y" LOGIN CONNECTION LIMIT 10 IN ROLE "a"', $changed->toString());
    }

    public function testWithOptionsReplacesTheCompleteOrderedRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $options = [new RoleAttribute(RoleCapability::Superuser, true), new ClearedPassword(), new RoleSystemId(7)];
        $changed = $statement->withOptions($options);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new RoleAttribute(RoleCapability::Login, true), new ConnectionLimit(10), new RoleMemberships([new NamedRole('a')])], $statement->options);
        self::assertEquals($options, $changed->options);
        self::assertSame('CREATE USER "app" SUPERUSER PASSWORD NULL SYSID 7', $changed->toString());
    }

    public function testWithOptionsCarriesABoundPasswordLiteral(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $secret = $binder->bind("CREATE ROLE r PASSWORD 'x'");
        self::assertInstanceOf(CreateRoleStatement::class, $secret);
        $password = $secret->options[0];
        self::assertInstanceOf(RolePassword::class, $password);
        $changed = $statement->withOptions([$password]);
        self::assertCount(1, $changed->options);
        self::assertInstanceOf(RolePassword::class, $changed->options[0]);
        self::assertSame($password->secret->text, $changed->options[0]->secret->text);
        self::assertSame("CREATE USER \"app\" PASSWORD 'x'", $changed->toString());
    }

    public function testWithOptionsRejectsContradictoryAttributes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new RoleAttribute(RoleCapability::Login, true), new RoleAttribute(RoleCapability::Login, false)]);
    }

    public function testWithOptionsRejectsARepeatedProperty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new RoleMemberships([new NamedRole('a')]), new RoleMemberships([new NamedRole('b')])]);
    }

    public function testWithKeywordReplacesTheDefinitionKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $changed = $statement->withKeyword(RoleKeyword::Group);
        self::assertNotSame($statement, $changed);
        self::assertSame(RoleKeyword::User, $statement->keyword);
        self::assertSame(RoleKeyword::Group, $changed->keyword);
        self::assertEquals($statement->options, $changed->options);
        self::assertSame('CREATE GROUP "app" LOGIN CONNECTION LIMIT 10 IN ROLE "a"', $changed->toString());
        self::assertSame('CREATE ROLE "app" LOGIN CONNECTION LIMIT 10 IN ROLE "a"', $statement->withKeyword(RoleKeyword::Role)->toString());
    }

    public function testWithOriginRetainsTheNameTheOptionsAndTheKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->options, $copy->options);
        self::assertSame($statement->keyword, $copy->keyword);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER app WITH LOGIN CONNECTION LIMIT 10 IN ROLE a');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testRejectsContradictoryAttributesOnConstruction(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin;
        $login = new RoleAttribute(RoleCapability::Login, true);
        $noLogin = new RoleAttribute(RoleCapability::Login, false);
        $this->expectException(InvalidStructure::class);
        new CreateRoleStatement($origin, new NamedRole('app'), [$login, $noLogin]);
    }
}
