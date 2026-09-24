<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\Casts;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Cast\CastMechanism;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Cast as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Casts::class)]
#[Medium]
final class CastsTest extends TestCase
{
    public function testCreateDistinguishesTheConversionForms(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(Statement\CreateFunctionCastStatement::class, $binder->bind('CREATE CAST (integer AS text) WITH FUNCTION f(integer)'));
        $inOut = $binder->bind('CREATE CAST (integer AS text) WITH INOUT');
        self::assertInstanceOf(Statement\CreateCastStatement::class, $inOut);
        self::assertSame(CastMechanism::InOut, $inOut->mechanism);
    }

    #[TestWith(['CREATE CAST (int4 AS integer) WITH INOUT'])]
    #[TestWith(['CREATE CAST (integer[] AS text) WITHOUT FUNCTION'])]
    public function testCreateRejectsAnImpossibleCast(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CastDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testDropReadsTheCastAndPolicies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP CAST IF EXISTS (integer AS text) CASCADE');
        self::assertInstanceOf(Statement\DropCastStatement::class, $statement);
        self::assertSame('text', $statement->cast->target->name);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
    }

    public function testTransformReadsBothDirections(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE OR REPLACE TRANSFORM FOR integer LANGUAGE plperl (TO SQL WITH FUNCTION t, FROM SQL WITH FUNCTION f)');
        self::assertInstanceOf(Statement\CreateTransformStatement::class, $statement);
        self::assertSame(['f'], $statement->fromSql?->name->parts);
        self::assertSame(['t'], $statement->toSql?->name->parts);
        self::assertTrue($statement->orReplace);
    }

    public function testDropTransformReadsTheTransform(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TRANSFORM IF EXISTS FOR integer LANGUAGE plperl');
        self::assertInstanceOf(Statement\DropTransformStatement::class, $statement);
        self::assertSame('plperl', $statement->transform->language);
        self::assertTrue($statement->ifExists);
    }

    public function testTypesSkipsFunctionSignatures(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE CAST (integer AS text) WITH FUNCTION f(bigint)'), ['CreateCastStmt'])[0];
        self::assertSame(['integer', 'text'], array_map(static fn ($type): string => $type->name, Casts::types($source)));
    }

    public function testLanguageReadsTheIdentifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP TRANSFORM FOR integer LANGUAGE "PlPerl"');
        self::assertInstanceOf(Statement\DropTransformStatement::class, $statement);
        self::assertSame('PlPerl', $statement->transform->language);
    }
}
