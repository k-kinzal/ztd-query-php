<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\Definition\Account\UserAlterations;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterDefaultRolesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\AlterUsersStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UserAlterations::class)]
#[Medium]
final class UserAlterationsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'ALTER USER a PASSWORD EXPIRE, CURRENT_USER() PASSWORD EXPIRE', "ALTER USER 'a' PASSWORD EXPIRE, CURRENT_USER PASSWORD EXPIRE"])]
    #[TestWith(['mysql-5.7.44', "ALTER USER IF EXISTS a IDENTIFIED WITH p AS 'h', b REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 2 ACCOUNT LOCK", "ALTER USER IF EXISTS 'a' IDENTIFIED WITH `p` AS 'h', 'b' REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 2 ACCOUNT LOCK"])]
    #[TestWith(['mysql-5.7.44', "ALTER USER USER() IDENTIFIED BY 'x'", "ALTER USER USER() IDENTIFIED BY 'x'"])]
    #[TestWith(['mysql-8.0.44', "ALTER USER a IDENTIFIED BY 'n' REPLACE 'o' RETAIN CURRENT PASSWORD, b DISCARD OLD PASSWORD", "ALTER USER 'a' IDENTIFIED BY 'n' REPLACE 'o' RETAIN CURRENT PASSWORD, 'b' DISCARD OLD PASSWORD"])]
    #[TestWith(['mysql-8.4.7', "ALTER USER USER() IDENTIFIED BY RANDOM PASSWORD REPLACE 'o'", "ALTER USER USER() IDENTIFIED BY RANDOM PASSWORD REPLACE 'o'"])]
    #[TestWith(['mysql-9.1.0', 'ALTER USER a ADD 2 FACTOR IDENTIFIED WITH p ADD 3 FACTOR IDENTIFIED BY RANDOM PASSWORD', "ALTER USER 'a' ADD 2 FACTOR IDENTIFIED WITH `p` ADD 3 FACTOR IDENTIFIED BY RANDOM PASSWORD"])]
    public function testBindWritesEveryReleaseFormBackAsAFixedPoint(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(AlterUsersStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindReadsDefaultRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER USER CURRENT_USER DEFAULT ROLE r, 's'@'h'");
        self::assertInstanceOf(AlterDefaultRolesStatement::class, $statement);
        self::assertSame(CurrentAccount::Authenticated, $statement->account);
        self::assertEquals([new AccountName('r'), new AccountName('s', 'h')], $statement->roles);
    }

    public function testExpiryListsPlainTargetsWithOneSharedPolicy(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('SELECT 1')->origin;
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.6.51'))->parse('ALTER USER a PASSWORD EXPIRE, b PASSWORD EXPIRE');
        $statement = UserAlterations::expiry($origin, $tree->find('alter_user_list')[0], new Identifiers(Dialect::MySql));
        self::assertEquals([new AccountTarget(new AccountName('a')), new AccountTarget(new AccountName('b'))], $statement->alterations);
        self::assertSame([AccountPolicy::ExpirePassword], $statement->policies);
    }

    public function testClientReadsTheConnectingAccountForms(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $discard = $binder->bind('ALTER USER USER() DISCARD OLD PASSWORD');
        $change = $binder->bind("ALTER USER USER() IDENTIFIED BY 'x' REPLACE 'y' RETAIN CURRENT PASSWORD");
        self::assertInstanceOf(AlterUsersStatement::class, $discard);
        self::assertInstanceOf(AlterUsersStatement::class, $change);
        self::assertEquals([new OldPasswordDiscard(ClientAccount::Connected)], $discard->alterations);
        self::assertInstanceOf(CredentialChange::class, $change->alterations[0]);
        self::assertSame("'y'", $change->alterations[0]->replacedPassword?->text);
        self::assertTrue($change->alterations[0]->retainCurrentPassword);
    }

    public function testAlterationClassifiesEachAccountChange(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("ALTER USER a, b IDENTIFIED WITH p, c IDENTIFIED WITH p AS 'h' RETAIN CURRENT PASSWORD, d DISCARD OLD PASSWORD");
        $changes = $tree->find('alter_user');
        self::assertEquals(new AccountTarget(new AccountName('a')), UserAlterations::alteration($changes[0], new Identifiers(Dialect::MySql)));
        self::assertEquals(new PluginChange(new AccountName('b'), 'p'), UserAlterations::alteration($changes[1], new Identifiers(Dialect::MySql)));
        self::assertInstanceOf(AuthenticationChange::class, UserAlterations::alteration($changes[2], new Identifiers(Dialect::MySql)));
        self::assertEquals(new OldPasswordDiscard(new AccountName('d')), UserAlterations::alteration($changes[3], new Identifiers(Dialect::MySql)));
    }

    public function testLegacyReadsAMySql57Change(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('ALTER USER a IDENTIFIED WITH p, b');
        $users = $tree->find('grant_user');
        self::assertEquals(new PluginChange(new AccountName('a'), 'p'), UserAlterations::legacy($users[0], new Identifiers(Dialect::MySql)));
        self::assertEquals(new AccountTarget(new AccountName('b')), UserAlterations::legacy($users[1], new Identifiers(Dialect::MySql)));
    }

    public function testClassifySeparatesCredentialPluginAndEncodedForms(): void
    {
        $hash = (new LiteralBinder(Dialect::MySql))->bind(new Token(0, 'TEXT_STRING', "'h'", 0));
        self::assertInstanceOf(Literal::class, $hash);
        self::assertInstanceOf(CredentialChange::class, UserAlterations::classify(new AccountName('a'), RandomPassword::Generated, $hash, true));
        self::assertInstanceOf(PluginChange::class, UserAlterations::classify(new AccountName('a'), new PluginIdentification('p'), null, false));
        self::assertInstanceOf(AuthenticationChange::class, UserAlterations::classify(new AccountName('a'), new PluginHashIdentification('p', $hash), null, true));
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['mysql-8.4.7', 'alter user a default role all', \SqlSemantics\Model\Statement\Definition\MySql\Account\AlterDefaultRolePolicyStatement::class, 'ALTER USER \'a\' DEFAULT ROLE ALL'])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER a DEFAULT ROLE NONE', \SqlSemantics\Model\Statement\Definition\MySql\Account\AlterDefaultRolePolicyStatement::class, 'ALTER USER \'a\' DEFAULT ROLE NONE'])]
    #[TestWith(['mysql-8.4.7', 'alter user a default role r1, r2', AlterDefaultRolesStatement::class, 'ALTER USER \'a\' DEFAULT ROLE \'r1\', \'r2\''])]
    #[TestWith(['mysql-8.4.7', 'ALTER USER a 2 FACTOR INITIATE REGISTRATION', \SqlSemantics\Model\Statement\Definition\MySql\Account\InitiateRegistrationStatement::class, 'ALTER USER \'a\' 2 FACTOR INITIATE REGISTRATION'])]
    #[TestWith(['mysql-8.4.7', 'alter user user() discard old password', AlterUsersStatement::class, 'ALTER USER USER() DISCARD OLD PASSWORD'])]
    #[TestWith(['mysql-9.1.0', 'alter user a add 2 factor identified with p', AlterUsersStatement::class, 'ALTER USER \'a\' ADD 2 FACTOR IDENTIFIED WITH `p`'])]
    #[TestWith(['mysql-9.1.0', 'alter user a drop 2 factor', AlterUsersStatement::class, 'ALTER USER \'a\' DROP 2 FACTOR'])]
    public function testBindReadsLowerCaseRoleRegistrationAndFactorForms(string $version, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
    }
}
