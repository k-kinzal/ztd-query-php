<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantAllPrivilegesStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Account\PrivilegeCommands;

#[CoversClass(PrivilegeCommands::class)]
#[Medium]
final class PrivilegeCommandsTest extends TestCase
{
    public function testWriteReturnsNullForAccountStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE USER a');
        self::assertNull(PrivilegeCommands::write($statement));
    }

    #[TestWith(['mysql-8.4.7', 'GRANT r TO u WITH ADMIN OPTION', "GRANT 'r' TO 'u' WITH ADMIN OPTION"])]
    #[TestWith(['mysql-5.7.44', "GRANT PROXY ON p TO u IDENTIFIED BY 'x' WITH GRANT OPTION", "GRANT PROXY ON 'p' TO 'u' IDENTIFIED BY 'x' WITH GRANT OPTION"])]
    #[TestWith(['mysql-8.4.7', 'REVOKE IF EXISTS r FROM u IGNORE UNKNOWN USER', "REVOKE IF EXISTS 'r' FROM 'u' IGNORE UNKNOWN USER"])]
    #[TestWith(['mysql-5.6.51', 'REVOKE ALL, GRANT OPTION FROM u', "REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'u'"])]
    #[TestWith(['mysql-8.0.44', 'REVOKE PROXY ON p FROM u', "REVOKE PROXY ON 'p' FROM 'u'"])]
    #[TestWith(['mysql-9.1.0', 'REVOKE ALL ON *.* FROM u', "REVOKE ALL PRIVILEGES ON *.* FROM 'u'"])]
    #[TestWith(['mysql-5.7.44', 'REVOKE SELECT, INSERT ON app.* FROM u', "REVOKE SELECT, INSERT ON `app`.* FROM 'u'"])]
    public function testWriteProducesAFixedPointForEveryPrivilegeStatement(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, PrivilegeCommands::write($statement)?->toString());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testGrantWritesSharedClausesInGrammarOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build());
        $named = $binder->bind('GRANT SELECT ON *.* TO u REQUIRE X509 WITH MAX_USER_CONNECTIONS 1 GRANT OPTION');
        $all = $binder->bind('GRANT ALL ON *.* TO u REQUIRE NONE');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $named);
        self::assertInstanceOf(GrantAllPrivilegesStatement::class, $all);
        self::assertSame("GRANT SELECT ON *.* TO 'u' REQUIRE X509 WITH GRANT OPTION MAX_USER_CONNECTIONS 1", PrivilegeCommands::grant($named)->toString());
        self::assertSame("GRANT ALL PRIVILEGES ON *.* TO 'u' REQUIRE NONE", PrivilegeCommands::grant($all)->toString());
    }

    public function testRevokeAddsTheExistencePolicy(): void
    {
        self::assertSame('REVOKE IF EXISTS', PrivilegeCommands::revoke(true)->toString());
        self::assertSame('REVOKE', PrivilegeCommands::revoke(false)->toString());
    }

    public function testIgnoreWritesTheUnknownUserPolicy(): void
    {
        self::assertSame('IGNORE UNKNOWN USER', PrivilegeCommands::ignore(true)[0]->toString());
        self::assertSame([], PrivilegeCommands::ignore(false));
    }

    #[TestWith(['mysql-8.4.7', 'GRANT r TO u', "GRANT 'r' TO 'u'"])]
    #[TestWith(['mysql-8.4.7', 'GRANT PROXY ON p TO u', "GRANT PROXY ON 'p' TO 'u'"])]
    #[TestWith(['mysql-8.4.7', 'REVOKE r FROM u', "REVOKE 'r' FROM 'u'"])]
    #[TestWith(['mysql-5.7.44', 'GRANT SELECT ON *.* TO u', "GRANT SELECT ON *.* TO 'u'"])]
    #[TestWith(['mysql-8.4.7', 'GRANT ALL ON *.* TO u', "GRANT ALL PRIVILEGES ON *.* TO 'u'"])]
    public function testWriteOmitsAbsentOptionalClauses(string $version, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertSame($expected, PrivilegeCommands::write($statement)?->toString());
    }
}
