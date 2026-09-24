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

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsEveryPasswordForm')]
    public function testBindReadsEveryPasswordForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement::class . ' => ' . $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsEveryPasswordForm(): iterable
    {
        return [
            'SET PASSWORD = \'x\' (MySql)' => [Dialect::MySql, null, [], 'SET PASSWORD = \'x\'', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetPasswordStatement => SET PASSWORD = \'x\''],
            'SET PASSWORD FOR u = \'x\' (MySql)' => [Dialect::MySql, null, [], 'SET PASSWORD FOR u = \'x\'', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetPasswordStatement => SET PASSWORD FOR \'u\' = \'x\''],
            'SET PASSWORD FOR \'u\'@\'h\' = \'x\' REPLACE \'y\' RETAIN CURRENT PASSWORD (MySql)' => [Dialect::MySql, null, [], 'SET PASSWORD FOR \'u\'@\'h\' = \'x\' REPLACE \'y\' RETAIN CURRENT PASSWORD', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetPasswordStatement => SET PASSWORD FOR \'u\'@\'h\' = \'x\' REPLACE \'y\' RETAIN CURRENT PASSWORD'],
            'SET PASSWORD FOR CURRENT_USER() = \'x\' (MySql)' => [Dialect::MySql, null, [], 'SET PASSWORD FOR CURRENT_USER() = \'x\'', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetPasswordStatement => SET PASSWORD = \'x\''],
            'SET PASSWORD TO RANDOM (MySql)' => [Dialect::MySql, null, [], 'SET PASSWORD TO RANDOM', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetRandomPasswordStatement => SET PASSWORD TO RANDOM'],
            'SET PASSWORD FOR u TO RANDOM REPLACE \'y\' (MySql)' => [Dialect::MySql, null, [], 'SET PASSWORD FOR u TO RANDOM REPLACE \'y\'', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetRandomPasswordStatement => SET PASSWORD FOR \'u\' TO RANDOM REPLACE \'y\''],
            'SET PASSWORD = password(\'x\') (MySql mysql-5.6.51)' => [Dialect::MySql, 'mysql-5.6.51', [], 'SET PASSWORD = password(\'x\')', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetDerivedPasswordStatement => SET PASSWORD = PASSWORD(\'x\')'],
            'SET PASSWORD FOR u = OLD_PASSWORD(\'x\') (MySql mysql-5.6.51)' => [Dialect::MySql, 'mysql-5.6.51', [], 'SET PASSWORD FOR u = OLD_PASSWORD(\'x\')', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetDerivedPasswordStatement => SET PASSWORD FOR \'u\' = OLD_PASSWORD(\'x\')'],
            'SET PASSWORD = \'*abc\' (MySql mysql-5.6.51)' => [Dialect::MySql, 'mysql-5.6.51', [], 'SET PASSWORD = \'*abc\'', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetPasswordHashStatement => SET PASSWORD = \'*abc\''],
            'SET PASSWORD FOR u@h = \'x\' (MySql mysql-5.7.44)' => [Dialect::MySql, 'mysql-5.7.44', [], 'SET PASSWORD FOR u@h = \'x\'', 'SqlSemantics\\Model\\Statement\\Configuration\\Password\\SetPasswordStatement => SET PASSWORD FOR \'u\'@\'h\' = \'x\''],
            'SET @a = 1 (MySql)' => [Dialect::MySql, null, [], 'SET @a = 1', 'SqlSemantics\\Model\\Statement\\Configuration\\SetStatement => SET @`a` = 1'],
        ];
    }


    public function testBindLeavesAListedPasswordVariableToTheSettingBinder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("SET GLOBAL sql_mode = 1, PASSWORD = '*x'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertSame("SET GLOBAL `sql_mode` = 1, GLOBAL `PASSWORD` = '*x'", $statement->toString());
    }
}
