<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\AccountDefinition;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Definition\Account\InitialAuthenticationDefinition;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateUsersStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Account\UserDefinitions;

#[CoversClass(UserDefinitions::class)]
#[Medium]
final class UserDefinitionsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', "CREATE USER a IDENTIFIED BY 'x', b", "CREATE USER 'a' IDENTIFIED BY 'x', 'b'"])]
    #[TestWith(['mysql-8.4.7', "CREATE USER IF NOT EXISTS a DEFAULT ROLE r REQUIRE SSL WITH MAX_QUERIES_PER_HOUR 1 ACCOUNT LOCK ATTRIBUTE '{}'", "CREATE USER IF NOT EXISTS 'a' DEFAULT ROLE 'r' REQUIRE SSL WITH MAX_QUERIES_PER_HOUR 1 ACCOUNT LOCK ATTRIBUTE '{}'"])]
    public function testWriteKeepsTheClauseOrderOfTheGrammar(string $version, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        self::assertSame($expected, UserDefinitions::write($statement)->toString());
    }

    public function testDefinitionWritesFactorsAndInitialAuthentication(): void
    {
        self::assertSame("'a' IDENTIFIED WITH `p` AND IDENTIFIED BY RANDOM PASSWORD", UserDefinitions::definition(new AccountDefinition(new AccountName('a'), new PluginIdentification('p'), [RandomPassword::Generated]))->toString());
        self::assertSame("'a' IDENTIFIED WITH `f` INITIAL AUTHENTICATION IDENTIFIED BY RANDOM PASSWORD", UserDefinitions::definition(new InitialAuthenticationDefinition(new AccountName('a'), 'f', RandomPassword::Generated))->toString());
        self::assertSame("'a'", UserDefinitions::definition(new AccountDefinition(new AccountName('a')))->toString());
    }
}
