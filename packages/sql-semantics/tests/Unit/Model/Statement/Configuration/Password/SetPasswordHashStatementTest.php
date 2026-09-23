<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetPasswordHashStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetPasswordHashStatement::class)]
#[Medium]
final class SetPasswordHashStatementTest extends TestCase
{
    public function testWithOriginPreservesTheCredentialRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*hash'");
        self::assertInstanceOf(SetPasswordHashStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*hash'");
        self::assertInstanceOf(SetPasswordHashStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite));
    }

    public function testWithAccountProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*hash'");
        self::assertInstanceOf(SetPasswordHashStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withAccount(new \SqlSemantics\Model\Configuration\Account\AccountName('other', 'localhost'));
        self::assertNotSame($statement, $copy);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\AccountName::class, $copy->account);
        self::assertSame('other', $copy->account->username);
        self::assertSame($original, $statement->toString());
    }

    public function testWithHashProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*hash'");
        self::assertInstanceOf(SetPasswordHashStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withHash($statement->hash);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->hash->text, $copy->hash->text);
        self::assertSame($original, $statement->toString());
    }
}
