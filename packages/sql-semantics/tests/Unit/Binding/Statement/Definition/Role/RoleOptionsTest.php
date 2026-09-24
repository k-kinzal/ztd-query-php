<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Definition\Role\RoleAdmins;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Definition\Role\RoleMembers;
use SqlSemantics\Model\Definition\Role\RoleMemberships;
use SqlSemantics\Model\Definition\Role\RolePassword;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Definition\Role\RoleValidity;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Role\RoleOptions::class)]
#[Medium]
final class RoleOptionsTest extends TestCase
{
    public function testDefinitionReadsEveryOptionInSourceOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE ROLE r WITH LOGIN SYSID 2 PASSWORD 'x' IN GROUP a, CURRENT_USER CONNECTION LIMIT -1 ROLE b ADMIN d VALID UNTIL 'infinity' NOINHERIT NOSUPERUSER CREATEDB NOCREATEROLE REPLICATION NOBYPASSRLS");
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertCount(14, $statement->options);
        self::assertEquals(new RoleAttribute(RoleCapability::Login, true), $statement->options[0]);
        self::assertEquals(new RoleSystemId(2), $statement->options[1]);
        $password = $statement->options[2];
        self::assertInstanceOf(RolePassword::class, $password);
        self::assertSame("'x'", $password->secret->text);
        self::assertEquals(new RoleMemberships([new NamedRole('a'), SessionRole::CurrentUser]), $statement->options[3]);
        self::assertEquals(new ConnectionLimit(-1), $statement->options[4]);
        self::assertEquals(new RoleMembers([new NamedRole('b')]), $statement->options[5]);
        self::assertEquals(new RoleAdmins([new NamedRole('d')]), $statement->options[6]);
        $validity = $statement->options[7];
        self::assertInstanceOf(RoleValidity::class, $validity);
        self::assertSame("'infinity'", $validity->until->text);
        self::assertEquals([new RoleAttribute(RoleCapability::Inherit, false), new RoleAttribute(RoleCapability::Superuser, false), new RoleAttribute(RoleCapability::CreateDb, true), new RoleAttribute(RoleCapability::CreateRole, false), new RoleAttribute(RoleCapability::Replication, true), new RoleAttribute(RoleCapability::BypassRls, false)], array_slice($statement->options, 8));
    }

    public function testDefinitionReadsNoOptionsWithoutAList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE USER u');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertSame([], $statement->options);
    }

    public function testAlterationReadsAttributesCredentialsLimitsAndMembers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER ROLE SESSION_USER WITH NOLOGIN PASSWORD NULL CONNECTION LIMIT 5 VALID UNTIL '2030-01-01' USER alice, CURRENT_USER");
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertCount(5, $statement->options);
        self::assertEquals(new RoleAttribute(RoleCapability::Login, false), $statement->options[0]);
        self::assertEquals(new ClearedPassword(), $statement->options[1]);
        self::assertEquals(new ConnectionLimit(5), $statement->options[2]);
        $validity = $statement->options[3];
        self::assertInstanceOf(RoleValidity::class, $validity);
        self::assertSame("'2030-01-01'", $validity->until->text);
        self::assertEquals(new RoleMembers([new NamedRole('alice'), SessionRole::CurrentUser]), $statement->options[4]);
    }

    public function testAlterationReadsNoOptionsWithoutAList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE r');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertSame([], $statement->options);
    }

    #[TestWith(['CREATE GROUP g WITH INHERIT INHERIT'])]
    #[TestWith(["CREATE ROLE r PASSWORD NULL PASSWORD 'x'"])]
    #[TestWith(['CREATE ROLE r LOGIN NOLOGIN'])]
    #[TestWith(['CREATE ROLE r IN ROLE a IN GROUP b'])]
    #[TestWith(['ALTER ROLE r USER a USER b'])]
    #[TestWith(['ALTER ROLE r CONNECTION LIMIT 1 CONNECTION LIMIT 2'])]
    public function testDistinctRejectsRepeatedOrContradictedOptions(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleOption->message());
        $binder->bind($sql);
    }

    public function testMembershipReadsSysidAndEachRoleList(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r SYSID 0 ROLE a, CURRENT_USER IN GROUP c ADMIN d, "e"');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertEquals([new RoleSystemId(0), new RoleMembers([new NamedRole('a'), SessionRole::CurrentUser]), new RoleMemberships([new NamedRole('c')]), new RoleAdmins([new NamedRole('d'), new NamedRole('e')])], $statement->options);
        $synonym = $binder->bind('CREATE ROLE r IN ROLE b');
        self::assertInstanceOf(CreateRoleStatement::class, $synonym);
        self::assertEquals([new RoleMemberships([new NamedRole('b')])], $synonym->options);
    }

    #[TestWith(['CREATE ROLE r ROLE public'])]
    #[TestWith(['CREATE ROLE r ADMIN none'])]
    #[TestWith(['CREATE ROLE r IN ROLE a, public'])]
    public function testMembershipRejectsPublicAndNone(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleReference->message());
        $binder->bind($sql);
    }

    #[TestWith(["CREATE ROLE r PASSWORD 'x'", "'x'"])]
    #[TestWith(['CREATE USER u WITH ENCRYPTED PASSWORD $$secret$$', '$$secret$$'])]
    public function testOptionReadsPlainAndEncryptedPasswordsAlike(string $sql, string $text): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertCount(1, $statement->options);
        $password = $statement->options[0];
        self::assertInstanceOf(RolePassword::class, $password);
        self::assertSame($text, $password->secret->text);
    }

    public function testOptionReadsAnAlteredEncryptedPassword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER USER u ENCRYPTED PASSWORD 'p'");
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertCount(1, $statement->options);
        $password = $statement->options[0];
        self::assertInstanceOf(RolePassword::class, $password);
        self::assertSame("'p'", $password->secret->text);
    }

    public function testOptionReadsPasswordNullAsACleared(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ROLE r PASSWORD NULL');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertEquals([new ClearedPassword()], $statement->options);
    }

    public function testOptionReadsTheInheritKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER ROLE r INHERIT');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertEquals([new RoleAttribute(RoleCapability::Inherit, true)], $statement->options);
    }

    #[TestWith(["CREATE ROLE r WITH UNENCRYPTED PASSWORD 'x'"])]
    #[TestWith(["ALTER ROLE r UNENCRYPTED PASSWORD 'x'"])]
    public function testOptionRejectsUnencryptedPasswords(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RolePasswordEncryption->message());
        $binder->bind($sql);
    }

    #[TestWith(['SUPERUSER', RoleCapability::Superuser, true])]
    #[TestWith(['NOSUPERUSER', RoleCapability::Superuser, false])]
    #[TestWith(['CREATEDB', RoleCapability::CreateDb, true])]
    #[TestWith(['NOCREATEDB', RoleCapability::CreateDb, false])]
    #[TestWith(['CREATEROLE', RoleCapability::CreateRole, true])]
    #[TestWith(['NOCREATEROLE', RoleCapability::CreateRole, false])]
    #[TestWith(['NOINHERIT', RoleCapability::Inherit, false])]
    #[TestWith(['LOGIN', RoleCapability::Login, true])]
    #[TestWith(['NOLOGIN', RoleCapability::Login, false])]
    #[TestWith(['REPLICATION', RoleCapability::Replication, true])]
    #[TestWith(['NOREPLICATION', RoleCapability::Replication, false])]
    #[TestWith(['BYPASSRLS', RoleCapability::BypassRls, true])]
    #[TestWith(['NOBYPASSRLS', RoleCapability::BypassRls, false])]
    #[TestWith(['"login"', RoleCapability::Login, true])]
    public function testAttributeReadsEachCapabilityWithItsNegation(string $word, RoleCapability $capability, bool $granted): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ROLE r WITH ' . $word);
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertEquals([new RoleAttribute($capability, $granted)], $statement->options);
    }

    #[TestWith(['CREATE ROLE r WITH FOO'])]
    #[TestWith(['CREATE ROLE r NOFOO'])]
    #[TestWith(['CREATE ROLE r WITH "LOGIN"'])]
    #[TestWith(['ALTER ROLE r "NoLogin"'])]
    public function testAttributeRejectsUnknownOrCaseFoldedWords(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleOption->message());
        $binder->bind($sql);
    }

    #[TestWith(['-1', -1])]
    #[TestWith(['0', 0])]
    #[TestWith(['+5', 5])]
    #[TestWith(['2147483647', 2147483647])]
    public function testIntegerReadsSignedConstantsWithinTheServerRange(string $number, int $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ROLE r WITH CONNECTION LIMIT ' . $number);
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertEquals([new ConnectionLimit($expected)], $statement->options);
    }

    #[TestWith(['CREATE ROLE r WITH CONNECTION LIMIT -28'])]
    #[TestWith(['ALTER ROLE r CONNECTION LIMIT -2'])]
    public function testIntegerRejectsValuesBelowTheMinimum(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RoleNumericOption->message());
        $binder->bind($sql);
    }

    public function testTextKeepsTheConstantSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE ROLE r VALID UNTIL \'infinity\' PASSWORD $$se\'\'cret$$');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $validity = $statement->options[0];
        self::assertInstanceOf(RoleValidity::class, $validity);
        self::assertSame("'infinity'", $validity->until->text);
        $password = $statement->options[1];
        self::assertInstanceOf(RolePassword::class, $password);
        self::assertSame('$$se\'\'cret$$', $password->secret->text);
    }

    public function testRolesReadsTheMemberListOfEachKeyword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $alteration = $binder->bind('ALTER ROLE r USER a, "CURRENT_USER", SESSION_USER');
        self::assertInstanceOf(AlterRoleStatement::class, $alteration);
        self::assertEquals([new RoleMembers([new NamedRole('a'), new NamedRole('CURRENT_USER'), SessionRole::SessionUser])], $alteration->options);
        $definition = $binder->bind('CREATE ROLE r ROLE a, b');
        self::assertInstanceOf(CreateRoleStatement::class, $definition);
        self::assertEquals([new RoleMembers([new NamedRole('a'), new NamedRole('b')])], $definition->options);
    }
}
