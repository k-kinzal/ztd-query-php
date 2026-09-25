<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Extension\CreateLanguageStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateLanguageStatement::class)]
#[Medium]
final class CreateLanguageStatementTest extends TestCase
{
    public function testBindsTheHandlersAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE OR REPLACE TRUSTED PROCEDURAL LANGUAGE pl HANDLER app.call INLINE app.run NO VALIDATOR');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        self::assertSame(['pl', true, true], [$statement->name, $statement->orReplace, $statement->trusted]);
        self::assertEquals([new QualifiedName(['app', 'call']), new QualifiedName(['app', 'run']), null], [$statement->handler, $statement->inline, $statement->validator]);
        self::assertSame('CREATE OR REPLACE TRUSTED LANGUAGE "pl" HANDLER "app"."call" INLINE "app"."run"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER call VALIDATOR vld');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE LANGUAGE "pl" HANDLER "call" VALIDATOR "vld"', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER call');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER call');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        self::assertSame('CREATE LANGUAGE "pl2" HANDLER "call"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName('pl2')));
        self::assertSame('pl', $statement->name);
    }

    public function testWithOrReplaceReplacesTheReplacementPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER call');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        self::assertSame('CREATE OR REPLACE LANGUAGE "pl" HANDLER "call"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrReplace(true)));
        self::assertFalse($statement->orReplace);
    }

    public function testWithTrustedReplacesTheTrust(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRUSTED LANGUAGE pl HANDLER call');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        self::assertSame('CREATE LANGUAGE "pl" HANDLER "call"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withTrusted(false)));
        self::assertTrue($statement->trusted);
    }

    public function testWithHandlerReplacesTheCallHandler(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER call');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        self::assertSame('CREATE LANGUAGE "pl" HANDLER "app"."h"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withHandler(new QualifiedName(['app', 'h']))));
        $this->expectException(InvalidStructure::class);
        $statement->withHandler(new QualifiedName(['a', 'b', 'c', 'd']));
    }

    public function testWithInlineReplacesTheInlineHandler(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER call INLINE run');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        self::assertSame('CREATE LANGUAGE "pl" HANDLER "call"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withInline(null)));
        self::assertEquals(new QualifiedName(['run']), $statement->inline);
    }

    public function testWithValidatorReplacesTheValidator(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE LANGUAGE pl HANDLER call');
        self::assertInstanceOf(CreateLanguageStatement::class, $statement);
        self::assertSame('CREATE LANGUAGE "pl" HANDLER "call" VALIDATOR "v"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withValidator(new QualifiedName(['v']))));
        self::assertNull($statement->validator);
    }
}
