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
use SqlSemantics\Binding\Statement\Definition\Account\Identifications;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Account\Identification\HashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginHashIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateUsersStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Identifications::class)]
#[Medium]
final class IdentificationsTest extends TestCase
{
    #[TestWith(["IDENTIFIED BY 'x'", PasswordIdentification::class])]
    #[TestWith(['IDENTIFIED BY RANDOM PASSWORD', RandomPassword::class])]
    #[TestWith(['IDENTIFIED WITH p', PluginIdentification::class])]
    #[TestWith(["IDENTIFIED WITH p AS 'h'", PluginHashIdentification::class])]
    #[TestWith(["IDENTIFIED WITH p BY 'x'", PluginPasswordIdentification::class])]
    #[TestWith(['IDENTIFIED WITH p BY RANDOM PASSWORD', PluginRandomPasswordIdentification::class])]
    public function testReadMapsEachRuleToOneCredentialForm(string $clause, string $class): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('CREATE USER u ' . $clause);
        self::assertSame($class, Identifications::read($tree->find('identification')[0], new Identifiers(Dialect::MySql))::class);
    }

    public function testReadRejectsAnEmptyPluginName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::AuthenticationPlugin->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE USER u IDENTIFIED WITH ''");
    }

    #[TestWith(['mysql-5.6.51', "IDENTIFIED BY PASSWORD '*h'", HashIdentification::class])]
    #[TestWith(['mysql-5.6.51', "IDENTIFIED BY 'x'", PasswordIdentification::class])]
    #[TestWith(['mysql-5.6.51', "IDENTIFIED WITH p AS 'h'", PluginHashIdentification::class])]
    #[TestWith(['mysql-5.7.44', 'IDENTIFIED WITH p', PluginIdentification::class])]
    #[TestWith(['mysql-5.7.44', "IDENTIFIED WITH p BY 'x'", PluginPasswordIdentification::class])]
    public function testLegacyReadsTheGrantUserTokenForms(string $version, string $clause, string $class): void
    {
        $tree = (new DialectParser(Dialect::MySql, $version))->parse('CREATE USER u ' . $clause);
        $grantUser = $tree->find('grant_user')[0];
        self::assertSame($class, get_debug_type(Identifications::legacy($grantUser, array_slice($grantUser->tokens(), 1), new Identifiers(Dialect::MySql))));
    }

    public function testLegacyReturnsNullWithoutCredential(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-5.7.44'))->parse('CREATE USER u');
        self::assertNull(Identifications::legacy($tree->find('grant_user')[0], [], new Identifiers(Dialect::MySql)));
    }

    public function testPluginDecodesAQuotedName(): void
    {
        $tree = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("CREATE USER u IDENTIFIED WITH 'auth''x'");
        self::assertSame("auth'x", Identifications::plugin($tree->find('identified_with_plugin')[0], new Identifiers(Dialect::MySql)));
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE USER u IDENTIFIED WITH 'auth''x'");
        self::assertInstanceOf(CreateUsersStatement::class, $statement);
        self::assertSame("CREATE USER 'u' IDENTIFIED WITH `auth'x`", $statement->toString());
    }
}
