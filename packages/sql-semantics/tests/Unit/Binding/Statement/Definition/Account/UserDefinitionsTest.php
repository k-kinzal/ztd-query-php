<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Account\UserDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\InitialAuthenticationDefinition;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateRolesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateUsersStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UserDefinitions::class)]
#[Medium]
final class UserDefinitionsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', "CREATE USER 'a'@'h' IDENTIFIED BY PASSWORD '*h', b", "CREATE USER 'a' @'h' IDENTIFIED BY PASSWORD '*h', 'b'"])]
    #[TestWith(['mysql-5.7.44', "CREATE USER IF NOT EXISTS a IDENTIFIED WITH p BY 'x' REQUIRE SSL WITH MAX_USER_CONNECTIONS 1 PASSWORD EXPIRE NEVER ACCOUNT LOCK", "CREATE USER IF NOT EXISTS 'a' IDENTIFIED WITH `p` BY 'x' REQUIRE SSL WITH MAX_USER_CONNECTIONS 1 PASSWORD EXPIRE NEVER ACCOUNT LOCK"])]
    #[TestWith(['mysql-8.0.44', "CREATE USER a IDENTIFIED BY RANDOM PASSWORD DEFAULT ROLE r, s@h PASSWORD REQUIRE CURRENT COMMENT 'c'", "CREATE USER 'a' IDENTIFIED BY RANDOM PASSWORD DEFAULT ROLE 'r', 's' @'h' PASSWORD REQUIRE CURRENT COMMENT 'c'"])]
    #[TestWith(['mysql-8.4.7', "CREATE USER a IDENTIFIED WITH p AND IDENTIFIED BY 'x' AND IDENTIFIED WITH q AS 'h'", "CREATE USER 'a' IDENTIFIED WITH `p` AND IDENTIFIED BY 'x' AND IDENTIFIED WITH `q` AS 'h'"])]
    #[TestWith(['mysql-9.1.0', "CREATE USER a IDENTIFIED WITH f INITIAL AUTHENTICATION IDENTIFIED WITH p AS 'h'", "CREATE USER 'a' IDENTIFIED WITH `f` INITIAL AUTHENTICATION IDENTIFIED WITH `p` AS 'h'"])]
    public function testBindWritesEveryReleaseFormBackAsAFixedPoint(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindReadsSharedClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER IF NOT EXISTS a, CURRENT_USER DEFAULT ROLE r ACCOUNT UNLOCK');
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        self::assertTrue($statement->ifNotExists);
        self::assertEquals([new AccountName('r')], $statement->defaultRoles);
        self::assertSame([AccountPolicy::Unlock], $statement->policies);
        self::assertSame(CurrentAccount::Authenticated, $statement->accounts[1]->account);
    }

    public function testDefinitionReadsAPasswordlessAccount(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('CREATE USER a IDENTIFIED WITH authentication_fido INITIAL AUTHENTICATION IDENTIFIED BY RANDOM PASSWORD');
        $definition = UserDefinitions::definition($tree->find('create_user')[0], new Identifiers(Dialect::MySql));
        self::assertInstanceOf(InitialAuthenticationDefinition::class, $definition);
        self::assertSame('authentication_fido', $definition->plugin);
        self::assertSame(RandomPassword::Generated, $definition->initialAuthentication);
    }

    public function testDefinitionReadsAdditionalFactors(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("CREATE USER a IDENTIFIED WITH p AS 'h' AND IDENTIFIED WITH q");
        $definition = UserDefinitions::definition($tree->find('create_user')[0], new Identifiers(Dialect::MySql));
        self::assertInstanceOf(AccountDefinition::class, $definition);
        self::assertInstanceOf(PluginHashIdentification::class, $definition->identification);
        self::assertEquals([new PluginIdentification('q')], $definition->additionalFactors);
    }

    public function testLegacyReadsTheAccountAndItsCredential(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.6.51'))->parse("CREATE USER 'a'@'h' IDENTIFIED BY PASSWORD '*h'");
        $definition = UserDefinitions::legacy($tree->find('grant_user')[0], new Identifiers(Dialect::MySql));
        self::assertEquals(new AccountName('a', 'h'), $definition->account);
        self::assertInstanceOf(HashIdentification::class, $definition->identification);
    }

    public function testGranteeKeepsAnAccountWithoutCredentialPlain(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse("GRANT SELECT ON *.* TO a, b IDENTIFIED BY 'x'");
        self::assertEquals(new AccountName('a'), UserDefinitions::grantee($tree->find('grant_user')[0], new Identifiers(Dialect::MySql)));
        self::assertInstanceOf(AccountDefinition::class, UserDefinitions::grantee($tree->find('grant_user')[1], new Identifiers(Dialect::MySql)));
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testRolesKeepNamesAndHostsApart(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind("CREATE ROLE IF NOT EXISTS r, 's'@'h'");
        self::assertInstanceOf(CreateRolesStatement::class, $statement);
        self::assertEquals([new AccountName('r'), new AccountName('s', 'h')], $statement->roles);
        self::assertSame("CREATE ROLE IF NOT EXISTS 'r', 's' @'h'", $statement->toString());
    }
}
