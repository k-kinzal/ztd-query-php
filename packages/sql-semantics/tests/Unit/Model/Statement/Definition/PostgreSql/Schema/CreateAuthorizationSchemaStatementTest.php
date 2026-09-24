<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Schema\CreateAuthorizationSchemaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateAuthorizationSchemaStatement::class)]
#[Medium]
final class CreateAuthorizationSchemaStatementTest extends TestCase
{
    public function testWithOriginRetainsTheOwner(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA AUTHORIZATION alice');
        self::assertInstanceOf(CreateAuthorizationSchemaStatement::class, $statement);
        self::assertEquals(new NamedRole('alice'), $statement->withOrigin($statement->origin)->owner);
    }

    public function testWithOwnerRenamesTheSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA AUTHORIZATION alice CREATE TABLE t (id integer)');
        self::assertInstanceOf(CreateAuthorizationSchemaStatement::class, $statement);
        self::assertSame('CREATE SCHEMA AUTHORIZATION "bob" CREATE TABLE "t"("id" integer)', $statement->withOwner(new NamedRole('bob'))->toString());
    }

    public function testWithIfNotExistsSelectsTheExistingSchemaPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA AUTHORIZATION alice');
        self::assertInstanceOf(CreateAuthorizationSchemaStatement::class, $statement);
        self::assertSame('CREATE SCHEMA IF NOT EXISTS AUTHORIZATION "alice"', $statement->withIfNotExists(true)->toString());
    }

    public function testWithElementsKeepsUnqualifiedNamesForASessionOwner(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE SCHEMA AUTHORIZATION CURRENT_USER');
        self::assertInstanceOf(CreateAuthorizationSchemaStatement::class, $statement);
        $source = $binder->bind('CREATE SCHEMA AUTHORIZATION SESSION_USER CREATE TABLE t (id integer)');
        self::assertInstanceOf(CreateAuthorizationSchemaStatement::class, $source);
        self::assertSame('CREATE SCHEMA AUTHORIZATION CURRENT_USER CREATE TABLE "t"("id" integer)', $statement->withElements($source->elements)->toString());
    }
}
