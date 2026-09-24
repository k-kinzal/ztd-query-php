<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast\CreateTransformStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateTransformStatement::class)]
#[Medium]
final class CreateTransformStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (TO SQL WITH FUNCTION to_h(internal), FROM SQL WITH FUNCTION from_h(internal))');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        self::assertSame('plperl', $statement->language);
        self::assertSame(['from_h'], $statement->fromSql?->name->parts);
        self::assertSame(['to_h'], $statement->toSql?->name->parts);
        self::assertFalse($statement->orReplace);
        self::assertSame('CREATE TRANSFORM FOR "hstore" LANGUAGE "plperl"(FROM SQL WITH FUNCTION "from_h"("internal"), TO SQL WITH FUNCTION "to_h"("internal"))', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsATransformWithoutFunctions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (TO SQL WITH FUNCTION f)');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withToSql(null);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (TO SQL WITH FUNCTION f)');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        self::assertSame('CREATE TRANSFORM FOR "hstore" LANGUAGE "plperl"(TO SQL WITH FUNCTION "f")', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (TO SQL WITH FUNCTION f)');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        self::assertSame('jsonb', $statement->withType(TypeDescriptor::builtin(Dialect::PostgreSql, 'jsonb'))->type->name);
        self::assertSame('hstore', $statement->type->name);
    }

    public function testWithLanguageReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (TO SQL WITH FUNCTION f)');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        self::assertSame('plpython3u', $statement->withLanguage('plpython3u')->language);
    }

    public function testWithFromSqlReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (TO SQL WITH FUNCTION f)');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        self::assertSame('CREATE TRANSFORM FOR "hstore" LANGUAGE "plperl"(FROM SQL WITH FUNCTION "g", TO SQL WITH FUNCTION "f")', $statement->withFromSql(new RoutineByName(new QualifiedName(['g'])))->toString());
    }

    public function testWithToSqlReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (FROM SQL WITH FUNCTION f)');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        self::assertSame(['g'], $statement->withToSql(new RoutineByName(new QualifiedName(['g'])))->toSql?->name->parts);
    }

    public function testWithOrReplaceReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TRANSFORM FOR hstore LANGUAGE plperl (FROM SQL WITH FUNCTION f)');
        self::assertInstanceOf(CreateTransformStatement::class, $statement);
        self::assertSame('CREATE OR REPLACE TRANSFORM FOR "hstore" LANGUAGE "plperl"(FROM SQL WITH FUNCTION "f")', $statement->withOrReplace(true)->toString());
    }
}
