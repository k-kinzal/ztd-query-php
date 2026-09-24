<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Relation\Identity\RestartIdentity::class)]
#[Medium]
final class RestartIdentityTest extends TestCase
{
    public function testBindsAndWritesTheAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id RESTART WITH 100', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Identity\SetColumnIdentity::class, $statement->actions[0]);
        self::assertInstanceOf(Relation\Identity\RestartIdentity::class, $statement->actions[0]->changes[0]);
        self::assertSame('100', $statement->actions[0]->changes[0]->value?->text);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" RESTART WITH 100', $statement->toString());
    }

    public function testRestartsAtTheStartValueWithoutAnOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id RESTART', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Identity\SetColumnIdentity::class, $statement->actions[0]);
        self::assertEquals(new Relation\Identity\RestartIdentity(null), $statement->actions[0]->changes[0]);
        self::assertSame('ALTER TABLE "t" ALTER COLUMN "id" RESTART', $statement->toString());
    }
}
