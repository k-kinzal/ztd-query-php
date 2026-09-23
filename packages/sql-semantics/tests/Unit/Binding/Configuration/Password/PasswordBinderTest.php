<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\Password\PasswordBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PasswordBinder::class)]
#[Medium]
final class PasswordBinderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\BoundStatement> $expected
     */
    #[DataProvider('providerForms')]
    public function testBindClassifiesVersionedCredentialForms(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf($expected, $statement);
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf($expected, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }

    /**
     * @return iterable<string, array{string, string, class-string<\SqlSemantics\Model\BoundStatement>}>
     */
    public static function providerForms(): iterable
    {
        yield '5.6 encoded' => ['mysql-5.6.51', "SET PASSWORD FOR CURRENT_USER = '*hash'", Statement\SetPasswordHashStatement::class];
        yield '5.6 configured hashing' => ['mysql-5.6.51', "SET PASSWORD = PASSWORD('clear')", Statement\SetDerivedPasswordStatement::class];
        yield '5.6 old hashing' => ['mysql-5.6.51', "SET PASSWORD FOR 'u'@'h' = OLD_PASSWORD('clear')", Statement\SetDerivedPasswordStatement::class];
        yield '5.7 cleartext' => ['mysql-5.7.44', "SET PASSWORD FOR CURRENT_USER = 'clear'", Statement\SetPasswordStatement::class];
        yield '5.7 deprecated spelling' => ['mysql-5.7.44', "SET PASSWORD = PASSWORD('clear')", Statement\SetPasswordStatement::class];
        yield '8.0 generated' => ['mysql-8.0.44', 'SET PASSWORD TO RANDOM', Statement\SetRandomPasswordStatement::class];
        yield '8.1 generated' => ['mysql-8.1.0', "SET PASSWORD FOR CURRENT_USER() TO RANDOM REPLACE 'old'", Statement\SetRandomPasswordStatement::class];
        yield '8.2 supplied' => ['mysql-8.2.0', "SET PASSWORD FOR 'u' = 'clear' RETAIN CURRENT PASSWORD", Statement\SetPasswordStatement::class];
        yield '8.3 supplied' => ['mysql-8.3.0', "SET PASSWORD = 'clear' REPLACE 'old' RETAIN CURRENT PASSWORD", Statement\SetPasswordStatement::class];
        yield '8.4 generated' => ['mysql-8.4.7', "SET PASSWORD FOR 'u'@'h' TO RANDOM RETAIN CURRENT PASSWORD", Statement\SetRandomPasswordStatement::class];
        yield '9.0 supplied' => ['mysql-9.0.1', "SET PASSWORD FOR CURRENT_USER() = 'clear'", Statement\SetPasswordStatement::class];
        yield '9.1 generated' => ['mysql-9.1.0', "SET PASSWORD TO RANDOM REPLACE 'old' RETAIN CURRENT PASSWORD", Statement\SetRandomPasswordStatement::class];
    }

    public function testAccountPreservesNamesInsteadOfEvaluatingCurrentUser(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET PASSWORD FOR 'CURRENT_USER'@'local@host' = 'new'");
        self::assertInstanceOf(Statement\SetPasswordStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\AccountName::class, $statement->account);
        self::assertSame('CURRENT_USER', $statement->account->username);
        self::assertSame('local@host', $statement->account->host);
        $current = $binder->bind("SET PASSWORD FOR CURRENT_USER() = 'new'");
        self::assertInstanceOf(Statement\SetPasswordStatement::class, $current);
        self::assertSame(\SqlSemantics\Model\Configuration\Account\CurrentAccount::Authenticated, $current->account);
    }

    public function testLiteralRetainsCredentialSpellingAndReplacementRole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'n''ew' REPLACE 'old' RETAIN CURRENT PASSWORD");
        self::assertInstanceOf(Statement\SetPasswordStatement::class, $statement);
        self::assertSame("'n''ew'", $statement->password->text);
        self::assertSame("'old'", $statement->currentPassword?->text);
        self::assertTrue($statement->retainCurrentPassword);
    }

    public function testOperationDoesNotTreatUserVariablePasswordAsAnAccountRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET @password = 'value'", strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[0]);
    }
}
