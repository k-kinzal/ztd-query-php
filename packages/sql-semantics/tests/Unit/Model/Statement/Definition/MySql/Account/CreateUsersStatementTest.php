<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Binder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateUsersStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateUsersStatement::class)]
#[Medium]
final class CreateUsersStatementTest extends TestCase
{
    public function testResultColumnsDescribeTheGeneratedPasswordRows(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $generated = $binder->bind('CREATE USER a, b IDENTIFIED WITH p BY RANDOM PASSWORD');
        $supplied = $binder->bind("CREATE USER a IDENTIFIED BY 'x'");
        self::assertInstanceOf(CreateUsersStatement::class, $generated);
        self::assertInstanceOf(CreateUsersStatement::class, $supplied);
        self::assertSame(['user', 'host', 'generated password', 'auth_factor'], array_column($generated->resultColumns(), 'name'));
        self::assertSame([], $supplied->resultColumns());
    }

    public function testWithAccountsReplacesTheDefinitionsWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER a ACCOUNT LOCK');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $changed = $statement->withAccounts([new AccountDefinition(new AccountName('b', 'h'), new PluginIdentification('auth_socket'), [RandomPassword::Generated])]);
        self::assertCount(1, $statement->accounts);
        self::assertSame("CREATE USER 'b'@'h' IDENTIFIED WITH `auth_socket` AND IDENTIFIED BY RANDOM PASSWORD ACCOUNT LOCK", $changed->toString());
    }

    public function testWithIfNotExistsReplacesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER a');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        self::assertSame("CREATE USER IF NOT EXISTS 'a'", $statement->withIfNotExists(true)->toString());
        self::assertFalse($statement->ifNotExists);
    }

    public function testWithIfNotExistsRejectsTheMySql56Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('CREATE USER a');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withIfNotExists(true);
    }

    public function testWithDefaultRolesAddsTheRoleClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER a');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        self::assertSame("CREATE USER 'a' DEFAULT ROLE 'r'@'%'", $statement->withDefaultRoles([new AccountName('r', '%')])->toString());
        self::assertSame([], $statement->defaultRoles);
    }

    public function testWithDefaultRolesRejectsTheMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('CREATE USER a');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDefaultRoles([new AccountName('r')]);
    }

    public function testWithRequirementReplacesTheConnectionRequirement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("CREATE USER a REQUIRE SUBJECT 's'");
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        self::assertSame("CREATE USER 'a' REQUIRE X509", $statement->withRequirement(ConnectionSecurity::X509)->toString());
        self::assertSame("CREATE USER 'a'", $statement->withRequirement(null)->toString());
    }

    public function testWithResourceLimitsReplacesTheLimits(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER a WITH MAX_USER_CONNECTIONS 2');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $changed = $statement->withResourceLimits([new ResourceLimit(ResourceLimitKind::QueriesPerHour, 10), new ResourceLimit(ResourceLimitKind::UpdatesPerHour, 0)]);
        self::assertSame("CREATE USER 'a' WITH MAX_QUERIES_PER_HOUR 10 MAX_UPDATES_PER_HOUR 0", $changed->toString());
        self::assertSame(2, $statement->resourceLimits[0]->value);
    }

    public function testWithResourceLimitsRejectsTheMySql56Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('CREATE USER a');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withResourceLimits([new ResourceLimit(ResourceLimitKind::QueriesPerHour, 1)]);
    }

    public function testWithPoliciesWritesDayUnitsForIntervals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER a');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $changed = $statement->withPolicies([AccountPolicy::Lock, new AccountLimit(AccountLimitKind::PasswordReuseDays, 30), new AccountLimit(AccountLimitKind::PasswordLockDays, 2)]);
        self::assertSame("CREATE USER 'a' ACCOUNT LOCK PASSWORD REUSE INTERVAL 30 DAY PASSWORD_LOCK_TIME 2", $changed->toString());
    }

    public function testWithPoliciesRejectsFailedLoginTrackingOnMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('CREATE USER a');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withPolicies([new AccountLimit(AccountLimitKind::FailedLoginAttempts, 3)]);
    }

    public function testWithAnnotationReplacesTheMetadata(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE USER a COMMENT 'c'");
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $text = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'{\"k\": 1}'", 0));
        self::assertInstanceOf(Literal::class, $text);
        self::assertSame("CREATE USER 'a' ATTRIBUTE '{\"k\": 1}'", $statement->withAnnotation(new AccountAnnotation(AnnotationForm::Attribute, $text))->toString());
        self::assertSame("CREATE USER 'a'", $statement->withAnnotation(null)->toString());
    }

    public function testWithOriginRetainsTheCompleteDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE USER IF NOT EXISTS a DEFAULT ROLE r REQUIRE SSL WITH MAX_USER_CONNECTIONS 1 ACCOUNT LOCK COMMENT 'c'");
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->annotation, $copy->annotation);
    }

    public function testWithOriginRejectsTheMySql57GrammarForMetadata(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE USER a COMMENT 'c'");
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
