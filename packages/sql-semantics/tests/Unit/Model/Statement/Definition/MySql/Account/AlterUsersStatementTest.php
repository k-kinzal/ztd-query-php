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
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterUsersStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterUsersStatement::class)]
#[Medium]
final class AlterUsersStatementTest extends TestCase
{
    public function testResultColumnsDescribeTheGeneratedPasswordRows(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $generated = $binder->bind('ALTER USER a, b MODIFY 2 FACTOR IDENTIFIED BY RANDOM PASSWORD');
        $locked = $binder->bind('ALTER USER a ACCOUNT LOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $generated);
        self::assertInstanceOf(AlterUsersStatement::class, $locked);
        self::assertSame(['user', 'host', 'generated password', 'auth_factor'], array_column($generated->resultColumns(), 'name'));
        self::assertSame([], $locked->resultColumns());
    }

    public function testWithAlterationsReplacesTheChangesWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a ACCOUNT LOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $changed = $statement->withAlterations([new FactorRemoval(new AccountName('b'), [AuthenticationFactor::Third]), new AccountTarget(new AccountName('c', 'h'))]);
        self::assertCount(1, $statement->alterations);
        self::assertSame("ALTER USER 'b' DROP 3 FACTOR, 'c'@'h' ACCOUNT LOCK", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithAlterationsRejectsTheClientAccountWithSharedClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a ACCOUNT LOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAlterations([new OldPasswordDiscard(ClientAccount::Connected)]);
    }

    public function testWithAlterationsRejectsAFactorRemovalOnMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER USER a ACCOUNT LOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAlterations([new FactorRemoval(new AccountName('b'), [AuthenticationFactor::Second])]);
    }

    public function testWithIfExistsReplacesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('ALTER USER a ACCOUNT LOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        self::assertSame("ALTER USER IF EXISTS 'a' ACCOUNT LOCK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIfExists(true)));
        self::assertFalse($statement->ifExists);
    }

    public function testWithIfExistsRejectsTheMySql56Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('ALTER USER a PASSWORD EXPIRE');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withIfExists(true);
    }

    public function testWithRequirementReplacesTheConnectionRequirement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a REQUIRE NONE');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        self::assertSame("ALTER USER 'a' REQUIRE SSL", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withRequirement(ConnectionSecurity::Ssl)));
        self::assertSame(ConnectionSecurity::None, $statement->requirement);
    }

    public function testWithResourceLimitsReplacesTheLimits(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a ACCOUNT UNLOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        self::assertSame("ALTER USER 'a' WITH MAX_CONNECTIONS_PER_HOUR 4 ACCOUNT UNLOCK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withResourceLimits([new ResourceLimit(ResourceLimitKind::ConnectionsPerHour, 4)])));
        self::assertSame([], $statement->resourceLimits);
    }

    public function testWithPoliciesReplacesThePolicies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a ACCOUNT UNLOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        self::assertSame("ALTER USER 'a' PASSWORD REQUIRE CURRENT DEFAULT", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withPolicies([AccountPolicy::DefaultCurrentPasswordRequirement])));
        self::assertSame([AccountPolicy::Unlock], $statement->policies);
    }

    public function testWithPoliciesRejectsOtherPoliciesOnMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('ALTER USER a PASSWORD EXPIRE');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withPolicies([]);
    }

    public function testWithAnnotationReplacesTheMetadata(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a ACCOUNT UNLOCK');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $text = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'ops'", 0));
        self::assertInstanceOf(Literal::class, $text);
        self::assertSame("ALTER USER 'a' ACCOUNT UNLOCK COMMENT 'ops'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withAnnotation(new AccountAnnotation(AnnotationForm::Comment, $text))));
        self::assertNull($statement->annotation);
    }

    public function testWithOriginRetainsTheMySql56Form(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('ALTER USER a PASSWORD EXPIRE, b PASSWORD EXPIRE');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame("ALTER USER 'a' PASSWORD EXPIRE, 'b' PASSWORD EXPIRE", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithAlterationsAcceptsTheClientAccountAlone(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DISCARD OLD PASSWORD');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        self::assertSame('ALTER USER USER() DISCARD OLD PASSWORD', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withAlterations([new OldPasswordDiscard(ClientAccount::Connected)])));
    }

    public function testWithAlterationsRejectsTheClientAccountAmongOthers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a DISCARD OLD PASSWORD');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAlterations([new OldPasswordDiscard(ClientAccount::Connected), new OldPasswordDiscard(new AccountName('b'))]);
    }

    public function testWithRequirementRejectsTheClientAccount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER USER() DISCARD OLD PASSWORD');
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRequirement(ConnectionSecurity::Ssl);
    }

    public function testWithResourceLimitsRejectsTheClientAccount(): void
    {
        $limits = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER a WITH MAX_QUERIES_PER_HOUR 1');
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER USER() DISCARD OLD PASSWORD');
        self::assertInstanceOf(AlterUsersStatement::class, $limits);
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withResourceLimits($limits->resourceLimits);
    }

    public function testWithAnnotationRejectsTheClientAccount(): void
    {
        $annotated = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER USER a COMMENT 'c'");
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER USER USER() DISCARD OLD PASSWORD');
        self::assertInstanceOf(AlterUsersStatement::class, $annotated);
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAnnotation($annotated->annotation);
    }
}
