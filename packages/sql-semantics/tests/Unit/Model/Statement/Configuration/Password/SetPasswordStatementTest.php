<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetPasswordStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetPasswordStatement::class)]
#[Medium]
final class SetPasswordStatementTest extends TestCase
{
    public function testWithOriginPreservesTheCredentialRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'");
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'");
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite));
    }

    public function testWithAccountProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'");
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withAccount(new \SqlSemantics\Model\Configuration\Account\AccountName('other', 'localhost'));
        self::assertNotSame($statement, $copy);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\AccountName::class, $copy->account);
        self::assertSame('other', $copy->account->username);
        self::assertSame($original, $statement->toString());
    }

    public function testWithPasswordProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'");
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withPassword($statement->password);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->password->text, $copy->password->text);
        self::assertSame($original, $statement->toString());
    }

    public function testWithCurrentPasswordProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'");
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withCurrentPassword(null);
        self::assertNotSame($statement, $copy);
        self::assertNull($copy->currentPassword);
        self::assertSame($original, $statement->toString());
    }

    public function testWithRetainCurrentPasswordProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD = 'new'");
        self::assertInstanceOf(SetPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withRetainCurrentPassword(true);
        self::assertNotSame($statement, $copy);
        self::assertTrue($copy->retainCurrentPassword);
        self::assertSame($original, $statement->toString());
    }
}
