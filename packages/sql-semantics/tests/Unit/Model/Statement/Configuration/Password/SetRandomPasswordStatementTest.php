<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Password\SetRandomPasswordStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetRandomPasswordStatement::class)]
#[Medium]
final class SetRandomPasswordStatementTest extends TestCase
{
    public function testWithOriginPreservesTheCredentialRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET PASSWORD TO RANDOM');
        self::assertInstanceOf(SetRandomPasswordStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET PASSWORD TO RANDOM');
        self::assertInstanceOf(SetRandomPasswordStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite));
    }

    public function testWithAccountProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET PASSWORD TO RANDOM');
        self::assertInstanceOf(SetRandomPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withAccount(new \SqlSemantics\Model\Configuration\Account\AccountName('other', 'localhost'));
        self::assertNotSame($statement, $copy);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\AccountName::class, $copy->account);
        self::assertSame('other', $copy->account->username);
        self::assertSame($original, $statement->toString());
    }

    public function testWithCurrentPasswordProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET PASSWORD TO RANDOM');
        self::assertInstanceOf(SetRandomPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withCurrentPassword(null);
        self::assertNotSame($statement, $copy);
        self::assertNull($copy->currentPassword);
        self::assertSame($original, $statement->toString());
    }

    public function testWithRetainCurrentPasswordProducesANewValidatedSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET PASSWORD TO RANDOM');
        self::assertInstanceOf(SetRandomPasswordStatement::class, $statement);
        $original = $statement->toString();
        $copy = $statement->withRetainCurrentPassword(true);
        self::assertNotSame($statement, $copy);
        self::assertTrue($copy->retainCurrentPassword);
        self::assertSame($original, $statement->toString());
    }

    public function testResultColumnsDescribeGeneratedValuesWithoutCreatingThem(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET PASSWORD FOR 'u' TO RANDOM");
        self::assertInstanceOf(SetRandomPasswordStatement::class, $statement);
        $columns = $statement->resultColumns();
        self::assertSame(['user', 'host', 'generated password', 'auth_factor'], array_column($columns, 'name'));
        self::assertSame('varchar', $columns[2]->expression->type->name);
        self::assertSame('bigint unsigned', $columns[3]->expression->type->name);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\GeneratedPasswordColumn::class, $columns[2]->expression);
        self::assertSame($statement->account, $columns[2]->expression->account);
        self::assertSame($statement->scopeId, $columns[2]->expression->scopeId);
        self::assertSame([], $columns[2]->expression->inputs());
    }
}
