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
use SqlSemantics\Binding\Statement\Definition\Account\PrivilegeRevocations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PrivilegeRevocations::class)]
#[Medium]
final class PrivilegeRevocationsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'REVOKE ALL, GRANT OPTION FROM u', Privilege\RevokeAllGrantsStatement::class, "REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'u'"])]
    #[TestWith(['mysql-5.6.51', 'REVOKE EXECUTE ON PROCEDURE p FROM u', Privilege\RevokePrivilegesStatement::class, "REVOKE EXECUTE ON PROCEDURE `p` FROM 'u'"])]
    #[TestWith(['mysql-5.7.44', 'REVOKE PROXY ON p FROM u, v', Privilege\RevokeProxyStatement::class, "REVOKE PROXY ON 'p' FROM 'u', 'v'"])]
    #[TestWith(['mysql-8.0.44', 'REVOKE IF EXISTS ALL ON *.* FROM u IGNORE UNKNOWN USER', Privilege\RevokeAllPrivilegesStatement::class, "REVOKE IF EXISTS ALL PRIVILEGES ON *.* FROM 'u' IGNORE UNKNOWN USER"])]
    #[TestWith(['mysql-8.4.7', 'REVOKE IF EXISTS r, s@h FROM u', Privilege\RevokeRolesStatement::class, "REVOKE IF EXISTS 'r', 's'@'h' FROM 'u'"])]
    #[TestWith(['mysql-9.1.0', 'REVOKE SELECT (a), DELETE ON t FROM CURRENT_USER', Privilege\RevokePrivilegesStatement::class, 'REVOKE SELECT (`a`), DELETE ON TABLE `t` FROM CURRENT_USER'])]
    public function testBindWritesEveryRevocationFormBackAsAFixedPoint(string $version, string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testBindRejectsARoutinePrivilegeOnATable(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PrivilegeLevel->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('REVOKE EXECUTE ON t FROM u');
    }

    public function testGranteesReadPlainAccounts(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('REVOKE r FROM a, CURRENT_USER');
        self::assertEquals([new AccountName('a'), CurrentAccount::Authenticated], PrivilegeRevocations::grantees($tree->find('revoke')[0], new Identifiers(Dialect::MySql)));
    }

    public function testGranteesRejectACredentialOnMySql56(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RevokedCredential->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("REVOKE SELECT ON *.* FROM u IDENTIFIED BY 'x'");
    }
}
