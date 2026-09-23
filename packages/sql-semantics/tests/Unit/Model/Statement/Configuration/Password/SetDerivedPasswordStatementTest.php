<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetDerivedPasswordStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetDerivedPasswordStatement::class)]
#[Medium]
final class SetDerivedPasswordStatementTest extends TestCase
{
    public function testWithOriginPreservesTheCredentialRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = PASSWORD('new')");
        self::assertInstanceOf(SetDerivedPasswordStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = PASSWORD('new')");
        self::assertInstanceOf(SetDerivedPasswordStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite));
    }

    public function testWithAccountProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = PASSWORD('new')");
        self::assertInstanceOf(SetDerivedPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withAccount(new \SqlSemantics\Model\Configuration\Account\AccountName('other', 'localhost'));
        self::assertNotSame($statement, $copy);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\AccountName::class, $copy->account);
        self::assertSame('other', $copy->account->username);
        self::assertSame($original, $statement->toString());
    }

    public function testWithPasswordProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = PASSWORD('new')");
        self::assertInstanceOf(SetDerivedPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withPassword($statement->password);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->password->text, $copy->password->text);
        self::assertSame($original, $statement->toString());
    }

    public function testWithDerivationProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = PASSWORD('new')");
        self::assertInstanceOf(SetDerivedPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withDerivation(\SqlSemantics\Model\Configuration\Account\PasswordDerivation::Pre41);
        self::assertNotSame($statement, $copy);
        self::assertSame(\SqlSemantics\Model\Configuration\Account\PasswordDerivation::Pre41, $copy->derivation);
        self::assertSame($original, $statement->toString());
    }
}
